<?php
/**
 * Property Spotlight Admin Handler
 *
 * @package Property_Spotlight
 */

defined('ABSPATH') || exit;

class Property_Spotlight_Admin {
    
    /**
     * API handler instance
     */
    private Property_Spotlight_API $api;
    
    /**
     * Constructor
     */
    public function __construct(Property_Spotlight_API $api) {
        $this->api = $api;
        
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('wp_ajax_property_spotlight_save', [$this, 'ajax_save_featured']);
        add_action('wp_ajax_property_spotlight_save_credentials', [$this, 'ajax_save_credentials']);
        add_action('wp_ajax_property_spotlight_save_style', [$this, 'ajax_save_style']);
        add_action('wp_ajax_property_spotlight_clear_cache', [$this, 'ajax_clear_cache']);
        add_action('wp_ajax_property_spotlight_get_listings', [$this, 'ajax_get_listings']);
        add_action('wp_ajax_property_spotlight_save_automation', [$this, 'ajax_save_automation']);
        add_action('wp_ajax_property_spotlight_export', [$this, 'ajax_export_settings']);
        add_action('wp_ajax_property_spotlight_import', [$this, 'ajax_import_settings']);
        add_action('wp_ajax_property_spotlight_save_access', [$this, 'ajax_save_access']);
    }
    
    /**
     * Check if current user can manage listings
     * 
     * Admins always have access. Other users need to be in allowed roles or allowed users list.
     */
    public function current_user_can_manage_listings(): bool {
        // Admins always have full access
        if (current_user_can('manage_options')) {
            return true;
        }
        
        $settings = get_option('property_spotlight_settings', []);
        $access = $settings['access'] ?? [];
        $allowed_roles = $access['allowed_roles'] ?? [];
        $allowed_users = $access['allowed_users'] ?? [];
        
        $user = wp_get_current_user();
        if (!$user->exists()) {
            return false;
        }
        
        // Check if user ID is in allowed users list
        if (in_array($user->ID, array_map('intval', $allowed_users), true)) {
            return true;
        }
        
        // Check if user has any of the allowed roles
        foreach ($user->roles as $role) {
            if (in_array($role, $allowed_roles, true)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get capability for menu access
     * 
     * Returns 'manage_options' if no access settings configured,
     * otherwise returns 'read' (lowest capability) since we handle permissions ourselves.
     */
    private function get_menu_capability(): string {
        $settings = get_option('property_spotlight_settings', []);
        $access = $settings['access'] ?? [];
        
        // If no custom access configured, require admin
        if (empty($access['allowed_roles']) && empty($access['allowed_users'])) {
            return 'manage_options';
        }
        
        // Use 'read' as base capability, we'll check actual permissions in render
        return 'read';
    }
    
    /**
     * Add admin menu page
     */
    public function add_admin_menu(): void {
        $menu_cap = $this->get_menu_capability();
        
        add_menu_page(
            __('Property Spotlight', 'property-spotlight'),
            __('Property Spotlight', 'property-spotlight'),
            $menu_cap,
            'property-spotlight',
            [$this, 'render_admin_page'],
            'dashicons-star-filled',
            30
        );
        
        // Add submenu items for each tab
        // WordPress automatically creates first submenu item same as main menu
        // We'll add additional submenu items with tab parameters
        
        // Settings tab - admin only
        add_submenu_page(
            'property-spotlight',
            __('Settings', 'property-spotlight'),
            __('Settings', 'property-spotlight'),
            'manage_options',
            'property-spotlight-settings',
            [$this, 'render_admin_page']
        );
        
        // Style tab - admin only
        add_submenu_page(
            'property-spotlight',
            __('Style', 'property-spotlight'),
            __('Style', 'property-spotlight'),
            'manage_options',
            'property-spotlight-style',
            [$this, 'render_admin_page']
        );
        
        // Docs tab - same as main menu (anyone with access)
        add_submenu_page(
            'property-spotlight',
            __('Documentation', 'property-spotlight'),
            __('Documentation', 'property-spotlight'),
            $menu_cap,
            'property-spotlight-docs',
            [$this, 'render_admin_page']
        );
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets(string $hook): void {
        // Load assets on main page and all submenu pages
        // Use strpos() to check for plugin slug - works regardless of translated menu title
        $is_plugin_page = (
            $hook === 'toplevel_page_property-spotlight' ||
            strpos($hook, 'property-spotlight') !== false
        );
        
        if (!$is_plugin_page) {
            return;
        }
        
        // Select2 (bundled locally for WordPress.org compliance)
        wp_enqueue_style(
            'select2',
            PROPERTY_SPOTLIGHT_PLUGIN_URL . 'assets/vendor/select2/select2.min.css',
            [],
            '4.1.0'
        );
        wp_enqueue_script(
            'select2',
            PROPERTY_SPOTLIGHT_PLUGIN_URL . 'assets/vendor/select2/select2.min.js',
            ['jquery'],
            '4.1.0',
            true
        );
        
        // Sortable
        wp_enqueue_script('jquery-ui-sortable');
        
        // Plugin assets
        wp_enqueue_style(
            'property-spotlight-admin',
            PROPERTY_SPOTLIGHT_PLUGIN_URL . 'assets/css/admin.css',
            ['select2'],
            PROPERTY_SPOTLIGHT_VERSION
        );
        
        wp_enqueue_script(
            'property-spotlight-admin',
            PROPERTY_SPOTLIGHT_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery', 'select2', 'jquery-ui-sortable'],
            PROPERTY_SPOTLIGHT_VERSION,
            true
        );
        
        $settings = get_option('property_spotlight_settings', []);
        
        // Determine active tab from page parameter or query string
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Tab navigation, not form processing
        $current_page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : 'property-spotlight';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Tab navigation, not form processing
        $tab_param = isset($_GET['tab']) ? sanitize_text_field(wp_unslash($_GET['tab'])) : '';
        
        // Map page slugs to tabs
        $active_tab = 'listings'; // Default
        if ($tab_param) {
            $active_tab = $tab_param;
        } elseif ($current_page === 'property-spotlight-settings') {
            $active_tab = 'settings';
        } elseif ($current_page === 'property-spotlight-style') {
            $active_tab = 'style';
        } elseif ($current_page === 'property-spotlight-docs') {
            $active_tab = 'docs';
        }
        
        wp_localize_script('property-spotlight-admin', 'propertySpotlight', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('property_spotlight_nonce'),
            'featuredIds' => $settings['featured_ids'] ?? [],
            'activeTab' => $active_tab,
            'strings' => [
                'saving' => __('Saving...', 'property-spotlight'),
                'saved' => __('Saved!', 'property-spotlight'),
                'error' => __('Error saving settings', 'property-spotlight'),
                'loading' => __('Loading listings...', 'property-spotlight'),
                'noResults' => __('No listings found', 'property-spotlight'),
                'searchPlaceholder' => __('Search listings by address or ID...', 'property-spotlight'),
                'removeConfirm' => __('Remove this listing from featured?', 'property-spotlight'),
                'confirmClear' => __('Are you sure you want to clear custom credentials?', 'property-spotlight'),
                'exporting' => __('Exporting...', 'property-spotlight'),
                'exported' => __('Settings exported', 'property-spotlight'),
                'importing' => __('Importing...', 'property-spotlight'),
                'startDate' => __('Start', 'property-spotlight'),
                'endDate' => __('End', 'property-spotlight'),
                'statusActive' => __('Active', 'property-spotlight'),
                'statusScheduled' => __('Scheduled', 'property-spotlight'),
                'statusExpired' => __('Expired', 'property-spotlight'),
            ],
        ]);
    }
    
    /**
     * Render admin page
     */
    public function render_admin_page(): void {
        // Check if user has permission to access this page
        if (!$this->current_user_can_manage_listings()) {
            wp_die(esc_html__('You do not have permission to access this page.', 'property-spotlight'));
        }
        
        $settings = get_option('property_spotlight_settings', []);
        $featured_ids = $settings['featured_ids'] ?? [];
        $data_url = $settings['data_url'] ?? '';
        $api_key = $settings['api_key'] ?? $settings['client_secret'] ?? ''; // Support legacy field
        
        // Check if using official plugin credentials
        $linear_options = get_option('linear_settings', []);
        $using_official = !empty($linear_options['data_url']) && !empty($linear_options['client_secret']);
        $has_own_credentials = !empty($data_url) && !empty($api_key);
        $is_configured = $this->api->is_configured();
        
        // Check if user is admin (for showing admin-only sections)
        $is_admin = current_user_can('manage_options');
        
        // Determine active tab from page parameter or query string
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Tab navigation, not form processing
        $current_page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : 'property-spotlight';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Tab navigation, not form processing
        $tab_param = isset($_GET['tab']) ? sanitize_text_field(wp_unslash($_GET['tab'])) : '';
        
        // Map page slugs to tabs
        $active_tab = 'listings'; // Default
        if ($tab_param) {
            $active_tab = $tab_param;
        } elseif ($current_page === 'property-spotlight-settings') {
            $active_tab = 'settings';
        } elseif ($current_page === 'property-spotlight-style') {
            $active_tab = 'style';
        } elseif ($current_page === 'property-spotlight-docs') {
            $active_tab = 'docs';
        }
        
        // Non-admins can only access listings and docs tabs
        if (!$is_admin && in_array($active_tab, ['settings', 'style'], true)) {
            $active_tab = 'listings';
        }
        ?>
        <div class="wrap property-spotlight-admin">
            <div class="property-spotlight-header">
                <h1><?php esc_html_e('Property Spotlight', 'property-spotlight'); ?></h1>
                <p class="description">
                    <?php esc_html_e('Select and order the property listings you want to feature on your website.', 'property-spotlight'); ?>
                </p>
            </div>
            
            <div class="property-spotlight-tabs">
                <!-- Tab Navigation -->
                <nav class="property-spotlight-tab-nav">
                    <button type="button" class="tab-button <?php echo $active_tab === 'listings' ? 'active' : ''; ?>" data-tab="listings">
                        <span class="dashicons dashicons-star-filled"></span>
                        <?php esc_html_e('Listings', 'property-spotlight'); ?>
                    </button>
                    <?php if ($is_admin): ?>
                    <button type="button" class="tab-button <?php echo $active_tab === 'settings' ? 'active' : ''; ?>" data-tab="settings">
                        <span class="dashicons dashicons-admin-settings"></span>
                        <?php esc_html_e('Settings', 'property-spotlight'); ?>
                    </button>
                    <button type="button" class="tab-button <?php echo $active_tab === 'style' ? 'active' : ''; ?>" data-tab="style">
                        <span class="dashicons dashicons-art"></span>
                        <?php esc_html_e('Style', 'property-spotlight'); ?>
                    </button>
                    <?php endif; ?>
                    <button type="button" class="tab-button <?php echo $active_tab === 'docs' ? 'active' : ''; ?>" data-tab="docs">
                        <span class="dashicons dashicons-book"></span>
                        <?php esc_html_e('Documentation', 'property-spotlight'); ?>
                    </button>
                </nav>
                
                <!-- Tab Content -->
                <div class="property-spotlight-tab-content">
                    
                    <!-- LISTINGS TAB -->
                    <div class="property-spotlight-tab-panel <?php echo $active_tab === 'listings' ? 'active' : ''; ?>" id="tab-listings">
                        <?php if ($is_configured): ?>
                        <!-- Add Featured Listing Section -->
                        <div class="property-spotlight-section">
                            <h2>
                                <span class="dashicons dashicons-plus-alt2"></span>
                                <?php esc_html_e('Add Featured Listing', 'property-spotlight'); ?>
                            </h2>
                            <p class="description">
                                <?php esc_html_e('Add listings to your featured list. Search from available listings or enter a listing ID manually.', 'property-spotlight'); ?>
                            </p>
                            
                            <div class="property-spotlight-add-methods">
                                <div class="method-dropdown">
                                    <label for="listing-selector">
                                        <?php esc_html_e('Search and select from available listings:', 'property-spotlight'); ?>
                                    </label>
                                    <select id="listing-selector" class="property-spotlight-select" style="width: 100%;">
                                        <option value=""><?php esc_html_e('Search listings...', 'property-spotlight'); ?></option>
                                    </select>
                                </div>
                                
                                <div class="method-divider">
                                    <span><?php esc_html_e('OR', 'property-spotlight'); ?></span>
                                </div>
                                
                                <div class="method-manual">
                                    <label for="manual-id">
                                        <?php esc_html_e('Or enter listing ID manually:', 'property-spotlight'); ?>
                                    </label>
                                    <div class="manual-input-group">
                                        <input type="text" id="manual-id" placeholder="<?php esc_attr_e('e.g., ABC123', 'property-spotlight'); ?>">
                                        <button type="button" id="add-manual" class="button">
                                            <?php esc_html_e('Add', 'property-spotlight'); ?>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Featured Listings Section -->
                        <div class="property-spotlight-section">
                            <h2>
                                <span class="dashicons dashicons-list-view"></span>
                                <?php esc_html_e('Featured Listings', 'property-spotlight'); ?>
                            </h2>
                            <p class="description">
                                <?php esc_html_e('Drag listings to reorder. The order here determines how they appear on your website.', 'property-spotlight'); ?>
                            </p>
                            
                            <div id="featured-listings" class="featured-listings-list">
                                <?php if (empty($featured_ids)): ?>
                                    <div class="no-featured">
                                        <?php esc_html_e('No featured listings yet. Use the options above to add your first listing.', 'property-spotlight'); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="property-spotlight-actions">
                                <button type="button" id="save-featured" class="button button-primary">
                                    <?php esc_html_e('Save Changes', 'property-spotlight'); ?>
                                </button>
                                <span id="save-status"></span>
                                <p class="description" style="margin-top: 8px; font-size: 13px; color: #646970;">
                                    <?php esc_html_e('Changes take effect immediately after saving.', 'property-spotlight'); ?>
                                </p>
                            </div>
                        </div>
                        <?php else: ?>
                        <!-- Not configured message -->
                        <div class="property-spotlight-section">
                            <div class="notice notice-warning inline">
                                <p>
                                    <strong><?php esc_html_e('API settings required.', 'property-spotlight'); ?></strong>
                                    <?php esc_html_e('Configure API settings in the Settings tab to start selecting featured listings.', 'property-spotlight'); ?>
                                </p>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- SETTINGS TAB -->
                    <div class="property-spotlight-tab-panel <?php echo $active_tab === 'settings' ? 'active' : ''; ?>" id="tab-settings">
                        
                        <!-- API Settings Section -->
                        <div class="property-spotlight-section property-spotlight-credentials">
                            <h2>
                                <span class="dashicons dashicons-admin-network"></span>
                                <?php esc_html_e('API Settings', 'property-spotlight'); ?>
                            </h2>
                    <p class="description">
                        <?php esc_html_e('Configure Linear.fi API credentials to fetch listings. If the official Linear plugin is installed, this plugin can use those credentials automatically. You can also configure separate credentials here.', 'property-spotlight'); ?>
                    </p>
                    
                    <?php if ($using_official && !$has_own_credentials): ?>
                        <div class="notice notice-info inline">
                            <p>
                                <strong><?php esc_html_e('Using official Linear plugin credentials.', 'property-spotlight'); ?></strong>
                                <?php esc_html_e('No separate configuration needed. You can optionally configure your own credentials below.', 'property-spotlight'); ?>
                            </p>
                        </div>
                    <?php elseif (!$is_configured): ?>
                        <div class="notice notice-warning inline">
                            <p>
                                <strong><?php esc_html_e('API credentials required.', 'property-spotlight'); ?></strong>
                                <?php esc_html_e('Configure API credentials below, or set up the official Linear plugin first.', 'property-spotlight'); ?>
                            </p>
                        </div>
                    <?php elseif ($has_own_credentials): ?>
                        <div class="notice notice-success inline">
                            <p>
                                <strong><?php esc_html_e('Using custom API credentials.', 'property-spotlight'); ?></strong>
                                <?php esc_html_e('Settings are independent from the official Linear plugin.', 'property-spotlight'); ?>
                            </p>
                        </div>
                    <?php endif; ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="data-url"><?php esc_html_e('Data URL', 'property-spotlight'); ?></label>
                            </th>
                            <td>
                                <input type="url" id="data-url" class="regular-text" 
                                       value="<?php echo esc_attr($data_url); ?>"
                                       placeholder="<?php echo $using_official ? esc_attr__('Using official plugin setting', 'property-spotlight') : 'https://data.linear.fi'; ?>">
                                <p class="description">
                                    <?php esc_html_e('Linear API data endpoint URL. Default: https://data.linear.fi. Only change if using a test or custom endpoint.', 'property-spotlight'); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="api-key"><?php esc_html_e('API Key', 'property-spotlight'); ?></label>
                            </th>
                            <td>
                                <input type="text" id="api-key" class="regular-text" 
                                       value="<?php echo esc_attr($api_key); ?>"
                                       placeholder="<?php echo $using_official ? esc_attr__('Using official plugin setting', 'property-spotlight') : 'LINEAR-API-KEY xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx'; ?>">
                                <p class="description">
                                    <?php esc_html_e('Your Linear API key (include the LINEAR-API-KEY prefix). Obtain from your Linear.fi account or support documentation. Keep this secure.', 'property-spotlight'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <button type="button" id="save-credentials" class="button">
                            <?php esc_html_e('Save API Settings', 'property-spotlight'); ?>
                        </button>
                        <?php if ($has_own_credentials): ?>
                            <button type="button" id="clear-credentials" class="button">
                                <?php esc_html_e('Clear (Use Official Plugin)', 'property-spotlight'); ?>
                            </button>
                        <?php endif; ?>
                        <?php if ($is_configured): ?>
                            <button type="button" id="clear-cache" class="button" title="<?php esc_attr_e('Clear cached listing data. Use this if listings aren\'t updating or after making changes to featured listings.', 'property-spotlight'); ?>">
                                <?php esc_html_e('Clear Cache', 'property-spotlight'); ?>
                            </button>
                            <p class="description" style="display: inline-block; margin-left: 8px;">
                                <?php esc_html_e('Use if listings aren\'t updating or after making changes.', 'property-spotlight'); ?>
                            </p>
                        <?php endif; ?>
                        <span id="credentials-status"></span>
                    </p>
                </div>
                
                <!-- Automation Settings Section -->
                <div class="property-spotlight-section property-spotlight-automation">
                    <h2><?php esc_html_e('Automation Settings', 'property-spotlight'); ?></h2>
                    <p class="description">
                        <?php esc_html_e('Automatically manage your featured listings: remove outdated ones, hide sold properties, and track user engagement.', 'property-spotlight'); ?>
                    </p>
                    
                    <?php
                    $auto_expire_days = (int) ($settings['auto_expire_days'] ?? 0);
                    $auto_remove_sold = !empty($settings['auto_remove_sold']);
                    $enable_analytics = !empty($settings['enable_analytics']);
                    $hide_on_single = !empty($settings['hide_on_single']);
                    ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="auto-expire-days"><?php esc_html_e('Auto-expire after', 'property-spotlight'); ?></label>
                            </th>
                            <td>
                                <input type="number" id="auto-expire-days" class="small-text" min="0" max="365"
                                       value="<?php echo esc_attr($auto_expire_days); ?>">
                                <?php esc_html_e('days', 'property-spotlight'); ?>
                                <p class="description">
                                    <?php esc_html_e('Automatically remove listings from featured after this many days. Set to 0 to disable. Common: 30 days for monthly rotation.', 'property-spotlight'); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Sold Listings', 'property-spotlight'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" id="auto-remove-sold" <?php checked($auto_remove_sold); ?>>
                                    <?php esc_html_e('Automatically hide sold/rented listings', 'property-spotlight'); ?>
                                </label>
                                <p class="description">
                                    <?php esc_html_e('Listings with status "sold" or "rented" will not be displayed. Checks status from API automatically.', 'property-spotlight'); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Analytics', 'property-spotlight'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" id="enable-analytics" <?php checked($enable_analytics); ?>>
                                    <?php esc_html_e('Enable click and impression tracking', 'property-spotlight'); ?>
                                </label>
                                <p class="description">
                                    <?php esc_html_e('Fires events to Google Analytics 4, GTM, and Matomo when listings are viewed or clicked. Tracks listing ID, address, and position.', 'property-spotlight'); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Single Listing Pages', 'property-spotlight'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" id="hide-on-single" <?php checked($hide_on_single); ?>>
                                    <?php esc_html_e('Hide on single listing pages', 'property-spotlight'); ?>
                                </label>
                                <p class="description">
                                    <?php esc_html_e('Automatically hide the spotlight when viewing an individual property listing. Use this when the shortcode is on a page that also displays single listings.', 'property-spotlight'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <button type="button" id="save-automation" class="button">
                            <?php esc_html_e('Save Automation Settings', 'property-spotlight'); ?>
                        </button>
                        <span id="automation-status"></span>
                    </p>
                </div>
                
                <!-- Import/Export Section -->
                <div class="property-spotlight-section property-spotlight-import-export">
                    <h2><?php esc_html_e('Import / Export', 'property-spotlight'); ?></h2>
                    <p class="description">
                        <?php esc_html_e('Backup your featured listings configuration or transfer to another site. Export includes all settings: featured listings, API credentials, style settings, and automation preferences (JSON format).', 'property-spotlight'); ?>
                    </p>
                    
                    <div class="import-export-actions">
                        <div class="export-action">
                            <button type="button" id="export-settings" class="button">
                                <?php esc_html_e('Export Settings', 'property-spotlight'); ?>
                            </button>
                            <p class="description">
                                <?php esc_html_e('Download a JSON file with all settings. Use before major changes or site migrations.', 'property-spotlight'); ?>
                            </p>
                        </div>
                        
                        <div class="import-action">
                            <input type="file" id="import-file" accept=".json" style="display:none;">
                            <button type="button" id="import-settings" class="button">
                                <?php esc_html_e('Import Settings', 'property-spotlight'); ?>
                            </button>
                            <span id="import-filename"></span>
                            <p class="description">
                                <?php esc_html_e('Upload a previously exported JSON file. This will replace all current settings. Export a backup first if needed.', 'property-spotlight'); ?>
                            </p>
                        </div>
                    </div>
                        <span id="import-export-status"></span>
                        </div>
                
                <!-- Access Control Section -->
                <div class="property-spotlight-section property-spotlight-access">
                    <h2>
                        <span class="dashicons dashicons-admin-users"></span>
                        <?php esc_html_e('Access Control', 'property-spotlight'); ?>
                    </h2>
                    <p class="description">
                        <?php esc_html_e('Control which users can manage featured listings. Administrators always have full access. Settings, Style, and API configuration remain admin-only.', 'property-spotlight'); ?>
                    </p>
                    
                    <?php
                    $access_settings = $settings['access'] ?? [];
                    $allowed_roles = $access_settings['allowed_roles'] ?? [];
                    $allowed_users = $access_settings['allowed_users'] ?? [];
                    
                    // Get available roles (exclude administrator)
                    $wp_roles = wp_roles();
                    $available_roles = [];
                    foreach ($wp_roles->roles as $role_slug => $role_data) {
                        if ($role_slug !== 'administrator') {
                            $available_roles[$role_slug] = translate_user_role($role_data['name']);
                        }
                    }
                    
                    // Get all users for the user selector (exclude current admin)
                    $all_users = get_users(['fields' => ['ID', 'display_name', 'user_email']]);
                    ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e('Allowed Roles', 'property-spotlight'); ?></th>
                            <td>
                                <fieldset>
                                    <?php foreach ($available_roles as $role_slug => $role_name): ?>
                                    <label style="display: block; margin-bottom: 8px;">
                                        <input type="checkbox" 
                                               name="access_roles[]" 
                                               value="<?php echo esc_attr($role_slug); ?>"
                                               <?php checked(in_array($role_slug, $allowed_roles, true)); ?>>
                                        <?php echo esc_html($role_name); ?>
                                    </label>
                                    <?php endforeach; ?>
                                </fieldset>
                                <p class="description">
                                    <?php esc_html_e('Users with these roles can manage featured listings.', 'property-spotlight'); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="access-users"><?php esc_html_e('Allowed Users', 'property-spotlight'); ?></label>
                            </th>
                            <td>
                                <select id="access-users" name="access_users[]" multiple="multiple" style="width: 100%; max-width: 400px;">
                                    <?php foreach ($all_users as $user): ?>
                                    <option value="<?php echo esc_attr($user->ID); ?>"
                                            <?php selected(in_array((int) $user->ID, array_map('intval', $allowed_users), true)); ?>>
                                        <?php echo esc_html($user->display_name . ' (' . $user->user_email . ')'); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description">
                                    <?php esc_html_e('Individual users who can manage featured listings (regardless of role).', 'property-spotlight'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <button type="button" id="save-access" class="button">
                            <?php esc_html_e('Save Access Settings', 'property-spotlight'); ?>
                        </button>
                        <span id="access-status"></span>
                    </p>
                </div>
                    </div>
                    
                    <!-- STYLE TAB -->
                    <div class="property-spotlight-tab-panel <?php echo $active_tab === 'style' ? 'active' : ''; ?>" id="tab-style">
                        
                        <!-- Style Settings Section -->
                        <div class="property-spotlight-section property-spotlight-style-settings">
                            <h2>
                                <span class="dashicons dashicons-admin-customizer"></span>
                                <?php esc_html_e('Style Settings', 'property-spotlight'); ?>
                            </h2>
                    <p class="description">
                        <?php esc_html_e('Customize the appearance of your listings. Colors are applied globally via CSS variables. Use presets for quick styling or customize individual colors.', 'property-spotlight'); ?>
                    </p>
                    
                    <?php
                    $style_settings = $settings['style'] ?? [];
                    $primary_color = $style_settings['primary_color'] ?? '#1a1a1a';
                    $accent_color = $style_settings['accent_color'] ?? '#0066cc';
                    $price_color = $style_settings['price_color'] ?? '#1a1a1a';
                    $featured_bg = $style_settings['featured_bg'] ?? '#c62828';
                    $border_radius = $style_settings['border_radius'] ?? '12';
                    ?>
                    
                    <table class="form-table property-spotlight-style-table">
                        <tr>
                            <th scope="row"><?php esc_html_e('Primary Color', 'property-spotlight'); ?></th>
                            <td>
                                <input type="color" id="style-primary-color" value="<?php echo esc_attr($primary_color); ?>">
                                <input type="text" id="style-primary-color-text" value="<?php echo esc_attr($primary_color); ?>" class="small-text">
                                <p class="description"><?php esc_html_e('Text and headings color. Use dark colors for light backgrounds, light colors for dark themes.', 'property-spotlight'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Accent Color', 'property-spotlight'); ?></th>
                            <td>
                                <input type="color" id="style-accent-color" value="<?php echo esc_attr($accent_color); ?>">
                                <input type="text" id="style-accent-color-text" value="<?php echo esc_attr($accent_color); ?>" class="small-text">
                                <p class="description"><?php esc_html_e('Links and hover states. Should complement your primary color and provide visual contrast.', 'property-spotlight'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Price Color', 'property-spotlight'); ?></th>
                            <td>
                                <input type="color" id="style-price-color" value="<?php echo esc_attr($price_color); ?>">
                                <input type="text" id="style-price-color-text" value="<?php echo esc_attr($price_color); ?>" class="small-text">
                                <p class="description"><?php esc_html_e('Price text color. Use a bold or contrasting color to make prices stand out.', 'property-spotlight'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Featured Background', 'property-spotlight'); ?></th>
                            <td>
                                <input type="color" id="style-featured-bg" value="<?php echo esc_attr($featured_bg); ?>">
                                <input type="text" id="style-featured-bg-text" value="<?php echo esc_attr($featured_bg); ?>" class="small-text">
                                <p class="description"><?php esc_html_e('Background for "featured" style cards (NÄYTEIKKUNA). Only visible when using style="featured" in shortcodes. Choose a vibrant color that contrasts with white text.', 'property-spotlight'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Border Radius', 'property-spotlight'); ?></th>
                            <td>
                                <input type="range" id="style-border-radius" min="0" max="24" value="<?php echo esc_attr($border_radius); ?>">
                                <span id="style-border-radius-value"><?php echo esc_attr($border_radius); ?>px</span>
                                <p class="description"><?php esc_html_e('Card corner roundness. Range: 0px (sharp) to 24px (very rounded). Lower values (0-6px) for professional, higher (16-24px) for softer look.', 'property-spotlight'); ?></p>
                            </td>
                        </tr>
                    </table>
                    
                    <h4><?php esc_html_e('Presets', 'property-spotlight'); ?></h4>
                    <p class="description" style="margin-bottom: 12px;">
                        <?php esc_html_e('Pre-configured color schemes inspired by popular Finnish property websites. You can customize individual colors after applying a preset.', 'property-spotlight'); ?>
                    </p>
                    <div class="style-presets">
                        <button type="button" class="button style-preset" data-preset="kiinteistokolmio">
                            <?php esc_html_e('Kiinteistökolmio', 'property-spotlight'); ?>
                        </button>
                        <button type="button" class="button style-preset" data-preset="oikotie">
                            <?php esc_html_e('Oikotie', 'property-spotlight'); ?>
                        </button>
                        <button type="button" class="button style-preset" data-preset="nettiauto">
                            <?php esc_html_e('Nettiauto', 'property-spotlight'); ?>
                        </button>
                        <button type="button" class="button style-preset" data-preset="minimal">
                            <?php esc_html_e('Minimal', 'property-spotlight'); ?>
                        </button>
                        <button type="button" class="button style-preset" data-preset="default">
                            <?php esc_html_e('Reset to Default', 'property-spotlight'); ?>
                        </button>
                    </div>
                    
                        <p class="submit">
                            <button type="button" id="save-style" class="button button-primary">
                                <?php esc_html_e('Save Style Settings', 'property-spotlight'); ?>
                            </button>
                            <span id="style-status"></span>
                        </p>
                        </div>
                    </div>
                    
                    <!-- DOCUMENTATION TAB -->
                    <div class="property-spotlight-tab-panel <?php echo $active_tab === 'docs' ? 'active' : ''; ?>" id="tab-docs">
                        
                        <!-- Quick Start Section -->
                        <div class="property-spotlight-section property-spotlight-usage">
                            <h2>
                                <span class="dashicons dashicons-admin-tools"></span>
                                <?php esc_html_e('Quick Start', 'property-spotlight'); ?>
                            </h2>
                            <p><?php esc_html_e('Get up and running in three simple steps:', 'property-spotlight'); ?></p>
                            <ol style="margin-left: 20px; line-height: 1.8;">
                                <li><?php esc_html_e('Configure your API settings in the', 'property-spotlight'); ?> <strong><?php esc_html_e('Settings', 'property-spotlight'); ?></strong> <?php esc_html_e('tab above', 'property-spotlight'); ?></li>
                                <li><?php esc_html_e('Add featured listings in the', 'property-spotlight'); ?> <strong><?php esc_html_e('Listings', 'property-spotlight'); ?></strong> <?php esc_html_e('tab', 'property-spotlight'); ?></li>
                                <li><?php esc_html_e('Use the shortcode below to display listings anywhere on your site', 'property-spotlight'); ?></li>
                            </ol>
                            <div style="background: #f0f6fc; border-left: 4px solid #2271b1; padding: 12px 16px; margin: 16px 0; border-radius: var(--radius-sm);">
                                <strong><?php esc_html_e('Most common use case:', 'property-spotlight'); ?></strong><br>
                                <code style="display: inline-block; margin-top: 8px; padding: 8px 12px; background: #fff; border: 1px solid #c3c4c7; border-radius: var(--radius-sm);">[property_spotlight limit="3"]</code>
                                <p style="margin: 8px 0 0 0; font-size: 13px; color: #646970;">
                                    <?php esc_html_e('This displays your first 3 featured listings in a responsive 3-column grid - perfect for homepage hero sections.', 'property-spotlight'); ?>
                                </p>
                            </div>
                        </div>
                        
                        <!-- Usage Section -->
                        <div class="property-spotlight-section property-spotlight-usage">
                            <h2>
                                <span class="dashicons dashicons-editor-help"></span>
                                <?php esc_html_e('Usage', 'property-spotlight'); ?>
                            </h2>
                            <p><?php esc_html_e('Property Spotlight offers multiple ways to display your featured listings. Choose the method that best fits your workflow:', 'property-spotlight'); ?></p>
                    
                    <h3><?php esc_html_e('Shortcode', 'property-spotlight'); ?></h3>
                    <p><?php esc_html_e('The shortcode is the most flexible method. Simply paste it into any post, page, or widget area. Works with both the Classic and Block editors.', 'property-spotlight'); ?></p>
                    <div style="background: #f6f7f7; padding: 12px 16px; border-radius: var(--radius-sm); margin: 12px 0;">
                        <code style="font-size: 14px;">[property_spotlight]</code>
                    </div>
                    <p style="margin-top: 12px; font-size: 13px; color: #646970;">
                        <?php esc_html_e('This basic shortcode displays all your featured listings in the default 3-column grid layout.', 'property-spotlight'); ?>
                    </p>
                    
                    <h4><?php esc_html_e('Shortcode Attributes', 'property-spotlight'); ?></h4>
                    <p style="margin-bottom: 12px; font-size: 13px; color: #646970;">
                        <?php esc_html_e('Customize the appearance and behavior of your listings with these attributes. All attributes are optional.', 'property-spotlight'); ?>
                    </p>
                    <table class="widefat">
                        <thead>
                            <tr>
                                <th style="width: 120px;"><?php esc_html_e('Attribute', 'property-spotlight'); ?></th>
                                <th><?php esc_html_e('Description', 'property-spotlight'); ?></th>
                                <th style="width: 200px;"><?php esc_html_e('Values', 'property-spotlight'); ?></th>
                                <th style="width: 100px;"><?php esc_html_e('Default', 'property-spotlight'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><code>limit</code></td>
                                <td><?php esc_html_e('Control how many listings appear. Useful for homepage hero sections or limiting sidebar widgets. Leave empty to show all featured listings.', 'property-spotlight'); ?></td>
                                <td><?php esc_html_e('Any number (1, 2, 3...)', 'property-spotlight'); ?></td>
                                <td><?php esc_html_e('All', 'property-spotlight'); ?></td>
                            </tr>
                            <tr>
                                <td><code>layout</code></td>
                                <td><?php esc_html_e('Choose how listings are arranged: grid for card layouts, list for vertical stacking, or carousel for interactive sliders.', 'property-spotlight'); ?></td>
                                <td><code>grid</code>, <code>list</code>, <code>carousel</code></td>
                                <td><code>grid</code></td>
                            </tr>
                            <tr>
                                <td><code>style</code></td>
                                <td><?php esc_html_e('Match your site\'s design with pre-built styles inspired by popular Finnish property sites. Featured style adds a colored overlay badge.', 'property-spotlight'); ?></td>
                                <td><code>default</code>, <code>featured</code>, <code>compact</code>, <code>dark</code></td>
                                <td><code>default</code></td>
                            </tr>
                            <tr>
                                <td><code>columns</code></td>
                                <td><?php esc_html_e('Number of columns in grid layout. Responsive: automatically adjusts on mobile devices. Use 1 column for mobile-first designs.', 'property-spotlight'); ?></td>
                                <td><code>1</code>, <code>2</code>, <code>3</code>, <code>4</code></td>
                                <td><code>3</code></td>
                            </tr>
                            <tr>
                                <td><code>title</code></td>
                                <td><?php esc_html_e('Add a section header above your listings. Perfect for sections like "NÄYTEIKKUNA" or "Featured Properties".', 'property-spotlight'); ?></td>
                                <td><?php esc_html_e('Any text', 'property-spotlight'); ?></td>
                                <td>—</td>
                            </tr>
                            <tr>
                                <td><code>class</code></td>
                                <td><?php esc_html_e('Add custom CSS classes for advanced styling. Useful for theme integration or custom design requirements.', 'property-spotlight'); ?></td>
                                <td><?php esc_html_e('Any CSS class name', 'property-spotlight'); ?></td>
                                <td>—</td>
                            </tr>
                            <tr>
                                <td><code>show_price</code></td>
                                <td><?php esc_html_e('Toggle price display. Set to false for teaser sections or when price information is displayed elsewhere.', 'property-spotlight'); ?></td>
                                <td><code>true</code>, <code>false</code></td>
                                <td><code>true</code></td>
                            </tr>
                            <tr>
                                <td><code>show_address</code></td>
                                <td><?php esc_html_e('Toggle address display. Useful for compact layouts where space is limited.', 'property-spotlight'); ?></td>
                                <td><code>true</code>, <code>false</code></td>
                                <td><code>true</code></td>
                            </tr>
                            <tr>
                                <td><code>show_image</code></td>
                                <td><?php esc_html_e('Toggle listing images. Hide images for text-only layouts or sidebar widgets with limited space.', 'property-spotlight'); ?></td>
                                <td><code>true</code>, <code>false</code></td>
                                <td><code>true</code></td>
                            </tr>
                            <tr>
                                <td><code>show_location</code></td>
                                <td><?php esc_html_e('Toggle city/location display. Useful when location is already clear from context or page title.', 'property-spotlight'); ?></td>
                                <td><code>true</code>, <code>false</code></td>
                                <td><code>true</code></td>
                            </tr>
                            <tr>
                                <td><code>show_details</code></td>
                                <td><?php esc_html_e('Toggle property details (type, rooms, area). Hide for minimal designs or when details are shown elsewhere on the page.', 'property-spotlight'); ?></td>
                                <td><code>true</code>, <code>false</code></td>
                                <td><code>true</code></td>
                            </tr>
                            <tr>
                                <td><code>lang</code></td>
                                <td><?php esc_html_e('Override automatic language detection. Use when you need listings in a specific language regardless of site language.', 'property-spotlight'); ?></td>
                                <td><code>fi</code>, <code>en</code>, <code>sv</code></td>
                                <td><?php esc_html_e('Auto-detected', 'property-spotlight'); ?></td>
                            </tr>
                            <tr>
                                <td><code>hide_on_single</code></td>
                                <td><?php esc_html_e('Hide spotlight on single listing pages. Useful when the shortcode is on a page that also displays individual listings.', 'property-spotlight'); ?></td>
                                <td><code>auto</code>, <code>true</code>, <code>false</code></td>
                                <td><code>auto</code></td>
                            </tr>
                        </tbody>
                    </table>
                    
                    <h4><?php esc_html_e('Common Examples', 'property-spotlight'); ?></h4>
                    <p style="margin-bottom: 12px; font-size: 13px; color: #646970;">
                        <?php esc_html_e('These examples cover the most common use cases. Copy and paste into your posts, pages, or widgets.', 'property-spotlight'); ?>
                    </p>
                    <div class="shortcode-examples">
                        <div class="example-item">
                            <strong><?php esc_html_e('Default display - All listings in 3-column grid:', 'property-spotlight'); ?></strong>
                            <p style="margin: 4px 0; font-size: 12px; color: #646970;"><?php esc_html_e('Perfect starting point. Shows all your featured listings in a responsive grid.', 'property-spotlight'); ?></p>
                            <code>[property_spotlight]</code>
                        </div>
                        <div class="example-item">
                            <strong><?php esc_html_e('Homepage hero - 3 featured properties:', 'property-spotlight'); ?></strong>
                            <p style="margin: 4px 0; font-size: 12px; color: #646970;"><?php esc_html_e('Ideal for homepage hero sections. Displays exactly 3 listings in a clean grid.', 'property-spotlight'); ?></p>
                            <code>[property_spotlight limit="3"]</code>
                        </div>
                        <div class="example-item">
                            <strong><?php esc_html_e('2-column layout:', 'property-spotlight'); ?></strong>
                            <p style="margin: 4px 0; font-size: 12px; color: #646970;"><?php esc_html_e('Wider cards, perfect for content areas with more horizontal space.', 'property-spotlight'); ?></p>
                            <code>[property_spotlight columns="2"]</code>
                        </div>
                        <div class="example-item">
                            <strong><?php esc_html_e('Vertical list layout:', 'property-spotlight'); ?></strong>
                            <p style="margin: 4px 0; font-size: 12px; color: #646970;"><?php esc_html_e('Stack listings vertically. Great for sidebars or narrow content areas.', 'property-spotlight'); ?></p>
                            <code>[property_spotlight layout="list"]</code>
                        </div>
                        <div class="example-item">
                            <strong><?php esc_html_e('Carousel slider:', 'property-spotlight'); ?></strong>
                            <p style="margin: 4px 0; font-size: 12px; color: #646970;"><?php esc_html_e('Interactive slider with navigation. Best for showcasing multiple listings in limited space.', 'property-spotlight'); ?></p>
                            <code>[property_spotlight layout="carousel"]</code>
                        </div>
                    </div>
                    
                    <h4><?php esc_html_e('Finnish Property Site Styles', 'property-spotlight'); ?></h4>
                    <p style="margin-bottom: 12px; font-size: 13px; color: #646970;">
                        <?php esc_html_e('Pre-built styles inspired by popular Finnish property websites. Perfect for matching your site\'s design language.', 'property-spotlight'); ?>
                    </p>
                    <div class="shortcode-examples">
                        <div class="example-item">
                            <strong><?php esc_html_e('NÄYTEIKKUNA - Featured style with colored badge:', 'property-spotlight'); ?></strong>
                            <p style="margin: 4px 0; font-size: 12px; color: #646970;"><?php esc_html_e('Nettiauto-inspired style with a prominent colored overlay badge. Perfect for highlighting premium listings.', 'property-spotlight'); ?></p>
                            <code>[property_spotlight style="featured" title="NÄYTEIKKUNA" limit="3"]</code>
                        </div>
                        <div class="example-item">
                            <strong><?php esc_html_e('NÄYTEIKKUNA - Clean showcase style:', 'property-spotlight'); ?></strong>
                            <p style="margin: 4px 0; font-size: 12px; color: #646970;"><?php esc_html_e('White cards with clean design. Professional look suitable for any property type.', 'property-spotlight'); ?></p>
                            <code>[property_spotlight style="default" title="NÄYTEIKKUNA" columns="3"]</code>
                        </div>
                        <div class="example-item">
                            <strong><?php esc_html_e('Compact cards - 4 columns:', 'property-spotlight'); ?></strong>
                            <p style="margin: 4px 0; font-size: 12px; color: #646970;"><?php esc_html_e('Smaller cards in a 4-column grid. Maximizes listings shown in limited vertical space.', 'property-spotlight'); ?></p>
                            <code>[property_spotlight style="compact" columns="4"]</code>
                        </div>
                        <div class="example-item">
                            <strong><?php esc_html_e('Dark theme:', 'property-spotlight'); ?></strong>
                            <p style="margin: 4px 0; font-size: 12px; color: #646970;"><?php esc_html_e('Dark cards with light text. Ideal for dark backgrounds or modern design aesthetics.', 'property-spotlight'); ?></p>
                            <code>[property_spotlight style="dark" columns="3"]</code>
                        </div>
                    </div>
                    
                    <h4><?php esc_html_e('Real-World Use Cases', 'property-spotlight'); ?></h4>
                    <p style="margin-bottom: 12px; font-size: 13px; color: #646970;">
                        <?php esc_html_e('Practical examples for specific page locations and design requirements.', 'property-spotlight'); ?>
                    </p>
                    <div class="shortcode-examples">
                        <div class="example-item">
                            <strong><?php esc_html_e('Homepage hero section:', 'property-spotlight'); ?></strong>
                            <p style="margin: 4px 0; font-size: 12px; color: #646970;"><?php esc_html_e('Full-width section with custom styling. Add your own CSS class for theme integration.', 'property-spotlight'); ?></p>
                            <code>[property_spotlight limit="3" layout="grid" columns="3" class="homepage-featured"]</code>
                        </div>
                        <div class="example-item">
                            <strong><?php esc_html_e('Sidebar widget:', 'property-spotlight'); ?></strong>
                            <p style="margin: 4px 0; font-size: 12px; color: #646970;"><?php esc_html_e('Compact text-only list perfect for narrow sidebars. Images hidden to save space.', 'property-spotlight'); ?></p>
                            <code>[property_spotlight limit="5" layout="list" show_image="false"]</code>
                        </div>
                        <div class="example-item">
                            <strong><?php esc_html_e('Full-width carousel banner:', 'property-spotlight'); ?></strong>
                            <p style="margin: 4px 0; font-size: 12px; color: #646970;"><?php esc_html_e('Interactive slider spanning full page width. Great for above-the-fold content.', 'property-spotlight'); ?></p>
                            <code>[property_spotlight layout="carousel" class="full-width-slider"]</code>
                        </div>
                        <div class="example-item">
                            <strong><?php esc_html_e('Mobile-optimized single column:', 'property-spotlight'); ?></strong>
                            <p style="margin: 4px 0; font-size: 12px; color: #646970;"><?php esc_html_e('Single column layout that works perfectly on all screen sizes. No responsive breakpoints needed.', 'property-spotlight'); ?></p>
                            <code>[property_spotlight columns="1" limit="4"]</code>
                        </div>
                        <div class="example-item">
                            <strong><?php esc_html_e('Minimal text-only list:', 'property-spotlight'); ?></strong>
                            <p style="margin: 4px 0; font-size: 12px; color: #646970;"><?php esc_html_e('Ultra-compact display showing only addresses. Perfect for simple navigation or teaser sections.', 'property-spotlight'); ?></p>
                            <code>[property_spotlight show_price="false" show_image="false" layout="list"]</code>
                        </div>
                    </div>
                    
                    <h3><?php esc_html_e('Gutenberg Block', 'property-spotlight'); ?></h3>
                    <p><?php esc_html_e('If you prefer a visual interface, use the Gutenberg block instead of the shortcode. Perfect for users who aren\'t comfortable with code.', 'property-spotlight'); ?></p>
                    <ol style="margin-left: 20px; line-height: 1.8;">
                        <li><?php esc_html_e('In the Block Editor, click the + button to add a new block', 'property-spotlight'); ?></li>
                        <li><?php esc_html_e('Search for "Property Spotlight" in the block inserter', 'property-spotlight'); ?></li>
                        <li><?php esc_html_e('Use the block settings panel to configure all options visually', 'property-spotlight'); ?></li>
                    </ol>
                    <p style="margin-top: 12px; font-size: 13px; color: #646970;">
                        <?php esc_html_e('The block provides the same options as the shortcode, but with a user-friendly interface. All settings are saved automatically.', 'property-spotlight'); ?>
                    </p>
                    
                    <h3><?php esc_html_e('PHP Template Usage', 'property-spotlight'); ?></h3>
                    <p><?php esc_html_e('For theme developers: embed listings directly in PHP templates using WordPress\'s do_shortcode function. This is the recommended method for custom theme development.', 'property-spotlight'); ?></p>
                    <div style="background: #f6f7f7; padding: 12px 16px; border-radius: var(--radius-sm); margin: 12px 0; font-family: Consolas, Monaco, monospace; font-size: 13px;">
                        &lt;?php echo do_shortcode('[property_spotlight limit="3"]'); ?&gt;
                    </div>
                    <p style="margin-top: 8px; font-size: 13px; color: #646970;">
                        <?php esc_html_e('Place this code in your theme\'s template files (e.g., front-page.php, page.php) where you want listings to appear.', 'property-spotlight'); ?>
                    </p>
                    
                    <h3><?php esc_html_e('CSS Customization', 'property-spotlight'); ?></h3>
                    <p><?php esc_html_e('The plugin uses a BEM (Block Element Modifier) naming convention for CSS classes, making it easy to target specific elements for custom styling. All classes are prefixed with', 'property-spotlight'); ?> <code>property-spotlight</code> <?php esc_html_e('to avoid conflicts with your theme.', 'property-spotlight'); ?></p>
                    <p style="margin-top: 8px; font-size: 13px; color: #646970;">
                        <?php esc_html_e('Add custom CSS in Appearance > Customize > Additional CSS, or in your theme\'s stylesheet. Here are the available classes:', 'property-spotlight'); ?>
                    </p>
                    <table class="widefat" style="margin-top: 10px;">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Class', 'property-spotlight'); ?></th>
                                <th><?php esc_html_e('Description', 'property-spotlight'); ?></th>
                            </tr>
                        </thead>
                            <tbody>
                            <tr>
                                <td><code>.property-spotlight</code></td>
                                <td><?php esc_html_e('Main wrapper container. Use this to style the overall listing section (margins, padding, background).', 'property-spotlight'); ?></td>
                            </tr>
                            <tr>
                                <td><code>.property-spotlight--grid</code></td>
                                <td><?php esc_html_e('Applied when layout="grid". Use to customize grid-specific styling (gap, alignment).', 'property-spotlight'); ?></td>
                            </tr>
                            <tr>
                                <td><code>.property-spotlight--list</code></td>
                                <td><?php esc_html_e('Applied when layout="list". Use to customize vertical list spacing and layout.', 'property-spotlight'); ?></td>
                            </tr>
                            <tr>
                                <td><code>.property-spotlight--carousel</code></td>
                                <td><?php esc_html_e('Applied when layout="carousel". Use to customize slider navigation, arrows, and transitions.', 'property-spotlight'); ?></td>
                            </tr>
                            <tr>
                                <td><code>.property-spotlight__item</code></td>
                                <td><?php esc_html_e('Individual listing card container. Perfect for customizing card appearance (borders, shadows, hover effects).', 'property-spotlight'); ?></td>
                            </tr>
                            <tr>
                                <td><code>.property-spotlight__image</code></td>
                                <td><?php esc_html_e('Image wrapper element. Use to customize image sizing, aspect ratio, or add overlay effects.', 'property-spotlight'); ?></td>
                            </tr>
                            <tr>
                                <td><code>.property-spotlight__content</code></td>
                                <td><?php esc_html_e('Content area containing text information. Use for typography, spacing, or background customization.', 'property-spotlight'); ?></td>
                            </tr>
                            <tr>
                                <td><code>.property-spotlight__title</code></td>
                                <td><?php esc_html_e('Listing address/title element. Customize font size, weight, color, or add hover effects.', 'property-spotlight'); ?></td>
                            </tr>
                            <tr>
                                <td><code>.property-spotlight__price</code></td>
                                <td><?php esc_html_e('Price display element. Perfect for highlighting prices with custom colors or typography.', 'property-spotlight'); ?></td>
                            </tr>
                            <tr>
                                <td><code>.property-spotlight__meta</code></td>
                                <td><?php esc_html_e('Meta information container (rooms, area, property type). Use for styling secondary information.', 'property-spotlight'); ?></td>
                            </tr>
                            </tbody>
                        </table>
                        
                        <h4 style="margin-top: 24px;"><?php esc_html_e('Example CSS Customization', 'property-spotlight'); ?></h4>
                        <p style="margin-bottom: 8px; font-size: 13px; color: #646970;">
                            <?php esc_html_e('Here\'s an example of customizing the listing cards:', 'property-spotlight'); ?>
                        </p>
                        <div style="background: #1d2327; color: #f0f0f1; padding: 16px; border-radius: var(--radius-sm); font-family: Consolas, Monaco, monospace; font-size: 12px; line-height: 1.6; overflow-x: auto;">
                            <span style="color: #9cdcfe;">.property-spotlight__item</span> {<br>
                            &nbsp;&nbsp;&nbsp;&nbsp;<span style="color: #dcdcaa;">border</span>: <span style="color: #ce9178;">2px solid #e0e0e0</span>;<br>
                            &nbsp;&nbsp;&nbsp;&nbsp;<span style="color: #dcdcaa;">transition</span>: <span style="color: #ce9178;">all 0.3s ease</span>;<br>
                            }<br><br>
                            <span style="color: #9cdcfe;">.property-spotlight__item:hover</span> {<br>
                            &nbsp;&nbsp;&nbsp;&nbsp;<span style="color: #dcdcaa;">transform</span>: <span style="color: #ce9178;">translateY(-4px)</span>;<br>
                            &nbsp;&nbsp;&nbsp;&nbsp;<span style="color: #dcdcaa;">box-shadow</span>: <span style="color: #ce9178;">0 8px 16px rgba(0,0,0,0.1)</span>;<br>
                            }
                        </div>
                        </div>
                        
                        <!-- Tips & Best Practices Section -->
                        <div class="property-spotlight-section property-spotlight-usage">
                            <h2>
                                <span class="dashicons dashicons-lightbulb"></span>
                                <?php esc_html_e('Tips & Best Practices', 'property-spotlight'); ?>
                            </h2>
                            
                            <h3><?php esc_html_e('Performance', 'property-spotlight'); ?></h3>
                            <ul style="margin-left: 20px; line-height: 1.8;">
                                <li><?php esc_html_e('Use the limit attribute to avoid loading too many listings at once. 3-6 listings is optimal for most pages.', 'property-spotlight'); ?></li>
                                <li><?php esc_html_e('The plugin caches API responses automatically. Clear cache in Settings if listings don\'t update immediately.', 'property-spotlight'); ?></li>
                                <li><?php esc_html_e('Images are loaded lazily by default for better page load performance.', 'property-spotlight'); ?></li>
                            </ul>
                            
                            <h3><?php esc_html_e('Design Recommendations', 'property-spotlight'); ?></h3>
                            <ul style="margin-left: 20px; line-height: 1.8;">
                                <li><?php esc_html_e('Use 3 columns for desktop, 2 for tablets, and 1 for mobile. The plugin handles responsive breakpoints automatically.', 'property-spotlight'); ?></li>
                                <li><?php esc_html_e('Match your site\'s color scheme using the Style tab settings. Colors are applied via CSS variables.', 'property-spotlight'); ?></li>
                                <li><?php esc_html_e('Combine the title attribute with style="featured" for eye-catching section headers like "NÄYTEIKKUNA".', 'property-spotlight'); ?></li>
                            </ul>
                            
                            <h3><?php esc_html_e('Common Use Cases', 'property-spotlight'); ?></h3>
                            <ul style="margin-left: 20px; line-height: 1.8;">
                                <li><strong><?php esc_html_e('Homepage:', 'property-spotlight'); ?></strong> <?php esc_html_e('Use limit="3" with grid layout for a hero section showcasing your best properties.', 'property-spotlight'); ?></li>
                                <li><strong><?php esc_html_e('Sidebar:', 'property-spotlight'); ?></strong> <?php esc_html_e('Use layout="list" with show_image="false" for a compact, text-only widget.', 'property-spotlight'); ?></li>
                                <li><strong><?php esc_html_e('Property Pages:', 'property-spotlight'); ?></strong> <?php esc_html_e('Use limit="4" with columns="2" to show related or similar properties.', 'property-spotlight'); ?></li>
                            </ul>
                            
                            <h3><?php esc_html_e('Troubleshooting', 'property-spotlight'); ?></h3>
                            <ul style="margin-left: 20px; line-height: 1.8;">
                                <li><?php esc_html_e('Listings not showing? Check that API credentials are configured in the Settings tab.', 'property-spotlight'); ?></li>
                                <li><?php esc_html_e('Images missing? Verify that listings have images in the Linear.fi system.', 'property-spotlight'); ?></li>
                                <li><?php esc_html_e('Styling issues? Use browser developer tools to inspect CSS classes and override as needed.', 'property-spotlight'); ?></li>
                                <li><?php esc_html_e('Shortcode not working? Ensure you\'re using square brackets [ ] not parentheses ( ).', 'property-spotlight'); ?></li>
                            </ul>
                        </div>
                    </div>
                    
                </div><!-- /.property-spotlight-tab-content -->
            </div><!-- /.property-spotlight-tabs -->
        </div><!-- /.property-spotlight-admin -->
        <?php
    }
    
    /**
     * AJAX: Save featured listings
     */
    public function ajax_save_featured(): void {
        check_ajax_referer('property_spotlight_nonce', 'nonce');
        
        if (!$this->current_user_can_manage_listings()) {
            wp_send_json_error(['message' => __('Permission denied', 'property-spotlight')]);
        }
        
        // Handle both old (array) and new (JSON string) formats
        $raw_ids = isset($_POST['featured_ids']) ? sanitize_text_field(wp_unslash($_POST['featured_ids'])) : '[]';
        
        if (is_string($raw_ids)) {
            $featured_ids = json_decode($raw_ids, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $featured_ids = [];
            }
        } else {
            // Legacy format (simple array)
            $featured_ids = array_map('sanitize_text_field', $raw_ids);
        }
        
        // Sanitize the new metadata format
        $sanitized = [];
        foreach ($featured_ids as $item) {
            if (is_array($item) && isset($item['id'])) {
                $sanitized[] = [
                    'id' => sanitize_text_field($item['id']),
                    'added' => isset($item['added']) ? absint($item['added']) : time(),
                    'expires' => isset($item['expires']) && $item['expires'] ? absint($item['expires']) : null,
                    'start' => isset($item['start']) && $item['start'] ? absint($item['start']) : null,
                    'end' => isset($item['end']) && $item['end'] ? absint($item['end']) : null,
                ];
            } elseif (is_string($item)) {
                // Legacy format - convert to new
                $sanitized[] = [
                    'id' => sanitize_text_field($item),
                    'added' => time(),
                    'expires' => null,
                    'start' => null,
                    'end' => null,
                ];
            }
        }
        
        $settings = get_option('property_spotlight_settings', []);
        $settings['featured_ids'] = $sanitized;
        
        update_option('property_spotlight_settings', $settings);
        
        // Clear cache
        $this->api->clear_cache();
        
        wp_send_json_success(['message' => __('Settings saved', 'property-spotlight')]);
    }
    
    /**
     * AJAX: Save API credentials
     */
    public function ajax_save_credentials(): void {
        check_ajax_referer('property_spotlight_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'property-spotlight')]);
        }
        
        $data_url = isset($_POST['data_url']) ? esc_url_raw(wp_unslash($_POST['data_url'])) : '';
        $api_key = isset($_POST['api_key']) ? sanitize_text_field(wp_unslash($_POST['api_key'])) : '';
        
        $settings = get_option('property_spotlight_settings', []);
        $settings['data_url'] = $data_url;
        $settings['api_key'] = $api_key;
        // Remove legacy field if exists
        unset($settings['client_secret']);
        
        update_option('property_spotlight_settings', $settings);
        
        // Clear cache since credentials changed
        $this->api->clear_cache();
        
        wp_send_json_success([
            'message' => __('API settings saved', 'property-spotlight'),
            'has_credentials' => !empty($data_url) && !empty($api_key),
        ]);
    }
    
    /**
     * AJAX: Save style settings
     */
    public function ajax_save_style(): void {
        check_ajax_referer('property_spotlight_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'property-spotlight')]);
        }
        
        $style = [
            'primary_color' => sanitize_hex_color(wp_unslash($_POST['primary_color'] ?? '#1a1a1a')) ?: '#1a1a1a',
            'accent_color' => sanitize_hex_color(wp_unslash($_POST['accent_color'] ?? '#0066cc')) ?: '#0066cc',
            'price_color' => sanitize_hex_color(wp_unslash($_POST['price_color'] ?? '#1a1a1a')) ?: '#1a1a1a',
            'featured_bg' => sanitize_hex_color(wp_unslash($_POST['featured_bg'] ?? '#c62828')) ?: '#c62828',
            'border_radius' => absint($_POST['border_radius'] ?? 12),
        ];
        
        $settings = get_option('property_spotlight_settings', []);
        $settings['style'] = $style;
        
        update_option('property_spotlight_settings', $settings);
        
        wp_send_json_success([
            'message' => __('Style settings saved', 'property-spotlight'),
        ]);
    }
    
    /**
     * AJAX: Clear API cache
     */
    public function ajax_clear_cache(): void {
        check_ajax_referer('property_spotlight_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'property-spotlight')]);
        }
        
        $this->api->clear_cache();
        
        wp_send_json_success([
            'message' => __('Cache cleared', 'property-spotlight'),
        ]);
    }
    
    /**
     * AJAX: Get all listings
     */
    public function ajax_get_listings(): void {
        check_ajax_referer('property_spotlight_nonce', 'nonce');
        
        if (!$this->current_user_can_manage_listings()) {
            wp_send_json_error(['message' => __('Permission denied', 'property-spotlight')]);
        }
        
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verified via check_ajax_referer above
        $lang = isset($_GET['lang']) ? sanitize_text_field(wp_unslash($_GET['lang'])) : 'fi';
        $listings = $this->api->get_all_listings($lang);
        
        if (is_wp_error($listings)) {
            wp_send_json_error(['message' => $listings->get_error_message()]);
        }
        
        wp_send_json_success($listings);
    }
    
    /**
     * AJAX: Save automation settings
     */
    public function ajax_save_automation(): void {
        check_ajax_referer('property_spotlight_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'property-spotlight')]);
        }
        
        $settings = get_option('property_spotlight_settings', []);
        
        $settings['auto_expire_days'] = isset($_POST['auto_expire_days']) ? absint($_POST['auto_expire_days']) : 0;
        $settings['auto_remove_sold'] = !empty($_POST['auto_remove_sold']);
        $settings['enable_analytics'] = !empty($_POST['enable_analytics']);
        $settings['hide_on_single'] = !empty($_POST['hide_on_single']);
        
        update_option('property_spotlight_settings', $settings);
        
        wp_send_json_success(['message' => __('Automation settings saved', 'property-spotlight')]);
    }
    
    /**
     * AJAX: Export settings as JSON
     */
    public function ajax_export_settings(): void {
        check_ajax_referer('property_spotlight_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'property-spotlight')]);
        }
        
        $settings = get_option('property_spotlight_settings', []);
        
        $export_data = [
            'version' => PROPERTY_SPOTLIGHT_VERSION,
            'exported' => gmdate('c'),
            'site_url' => get_site_url(),
            'settings' => $settings,
        ];
        
        wp_send_json_success($export_data);
    }
    
    /**
     * AJAX: Import settings from JSON
     */
    public function ajax_import_settings(): void {
        check_ajax_referer('property_spotlight_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'property-spotlight')]);
        }
        
        $json_data = isset($_POST['import_data']) ? sanitize_textarea_field(wp_unslash($_POST['import_data'])) : '';
        
        if (empty($json_data)) {
            wp_send_json_error(['message' => __('No data provided', 'property-spotlight')]);
        }
        
        $import = json_decode($json_data, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error(['message' => __('Invalid JSON format', 'property-spotlight')]);
        }
        
        if (!isset($import['settings']) || !is_array($import['settings'])) {
            wp_send_json_error(['message' => __('Invalid settings format', 'property-spotlight')]);
        }
        
        $settings = $import['settings'];
        
        // Validate and sanitize imported settings
        $valid_settings = [
            'featured_ids' => [],
            'cache_ttl' => 1800,
            'data_url' => '',
            'api_key' => '',
            'auto_expire_days' => 0,
            'auto_remove_sold' => false,
            'enable_analytics' => false,
            'style' => [],
        ];
        
        // Only import known settings
        foreach ($valid_settings as $key => $default) {
            if (isset($settings[$key])) {
                if ($key === 'featured_ids' && is_array($settings[$key])) {
                    // Migrate to new format if needed
                    $valid_settings[$key] = property_spotlight_migrate_featured_ids($settings[$key]);
                } elseif ($key === 'style' && is_array($settings[$key])) {
                    $valid_settings[$key] = $settings[$key];
                } elseif ($key === 'data_url') {
                    $valid_settings[$key] = esc_url_raw($settings[$key]);
                } elseif ($key === 'api_key') {
                    $valid_settings[$key] = sanitize_text_field($settings[$key]);
                } elseif (is_bool($default)) {
                    $valid_settings[$key] = (bool) $settings[$key];
                } elseif (is_int($default)) {
                    $valid_settings[$key] = absint($settings[$key]);
                } else {
                    $valid_settings[$key] = $settings[$key];
                }
            }
        }
        
        update_option('property_spotlight_settings', $valid_settings);
        
        // Clear cache after import
        $this->api->clear_cache();
        
        $import_version = $import['version'] ?? 'unknown';
        wp_send_json_success([
            'message' => sprintf(
                // translators: %s is the version number from the imported settings
                __('Settings imported successfully from version %s', 'property-spotlight'),
                $import_version
            ),
        ]);
    }
    
    /**
     * AJAX: Save access control settings
     */
    public function ajax_save_access(): void {
        check_ajax_referer('property_spotlight_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'property-spotlight')]);
        }
        
        $settings = get_option('property_spotlight_settings', []);
        
        // Sanitize allowed roles
        $allowed_roles = [];
        if (isset($_POST['allowed_roles']) && is_array($_POST['allowed_roles'])) {
            $wp_roles = wp_roles();
            $raw_roles = array_map('sanitize_text_field', wp_unslash($_POST['allowed_roles']));
            foreach ($raw_roles as $role) {
                if ($role !== 'administrator' && isset($wp_roles->roles[$role])) {
                    $allowed_roles[] = $role;
                }
            }
        }
        
        // Sanitize allowed users
        $allowed_users = [];
        if (isset($_POST['allowed_users']) && is_array($_POST['allowed_users'])) {
            $raw_users = array_map('absint', wp_unslash($_POST['allowed_users']));
            foreach ($raw_users as $user_id) {
                if ($user_id > 0 && get_user_by('ID', $user_id)) {
                    $allowed_users[] = $user_id;
                }
            }
        }
        
        $settings['access'] = [
            'allowed_roles' => $allowed_roles,
            'allowed_users' => $allowed_users,
        ];
        
        update_option('property_spotlight_settings', $settings);
        
        wp_send_json_success(['message' => __('Access settings saved', 'property-spotlight')]);
    }
}
