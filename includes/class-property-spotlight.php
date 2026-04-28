<?php
/**
 * Core Property Spotlight class
 *
 * @package Property_Spotlight
 */

defined('ABSPATH') || exit;

class Property_Spotlight {
    
    /**
     * Singleton instance
     */
    private static ?Property_Spotlight $instance = null;
    
    /**
     * API handler instance
     */
    private Property_Spotlight_API $api;
    
    /**
     * Admin handler instance
     */
    private ?Property_Spotlight_Admin $admin = null;
    
    /**
     * Shortcode handler instance
     */
    private Property_Spotlight_Shortcode $shortcode;
    
    /**
     * Block handler instance
     */
    private Property_Spotlight_Block $block;
    
    /**
     * Plugin options
     *
     * @var array<string, mixed>
     */
    private array $options;
    
    /**
     * Get singleton instance
     *
     * @return Property_Spotlight
     */
    public static function get_instance(): Property_Spotlight {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Private constructor for singleton
     */
    private function __construct() {
        $this->options = get_option('property_spotlight_settings', [
            'featured_ids' => [],
            'cache_ttl' => 1800,
        ]);
        
        $this->init();
    }
    
    /**
     * Initialize plugin components
     */
    private function init(): void {
        // Initialize API handler
        $this->api = new Property_Spotlight_API();
        
        // Initialize admin if in admin area
        if (is_admin()) {
            $this->admin = new Property_Spotlight_Admin($this->api);
        }
        
        // Initialize shortcode handler
        $this->shortcode = new Property_Spotlight_Shortcode($this->api);
        
        // Initialize block handler
        $this->block = new Property_Spotlight_Block($this->api);
        
        // Register REST API endpoints
        add_action('rest_api_init', [$this, 'register_rest_routes']);
        
        // Enqueue frontend assets
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
    }
    
    /**
     * Register REST API routes
     */
    public function register_rest_routes(): void {
        register_rest_route('property-spotlight/v1', '/listings', [
            'methods' => 'GET',
            'callback' => [$this, 'rest_get_all_listings'],
            'permission_callback' => fn() => Property_Spotlight_Admin::user_can_manage_listings(),
            'args' => [
                'lang' => [
                    'type' => 'string',
                    'required' => false,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);
        
        // Public by design: same data is rendered publicly by the [property_spotlight] shortcode.
        register_rest_route('property-spotlight/v1', '/featured', [
            'methods' => 'GET',
            'callback' => [$this, 'rest_get_featured_listings'],
            // Public: exposes only listing IDs, no PII. Same data rendered by [property_spotlight] shortcode.
            'permission_callback' => '__return_true',
            'args' => [
                'lang' => [
                    'type' => 'string',
                    'required' => false,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'limit' => [
                    'type' => 'integer',
                    'required' => false,
                    'default' => 0,
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);
        
        register_rest_route('property-spotlight/v1', '/settings', [
            'methods' => 'GET',
            'callback' => [$this, 'rest_get_settings'],
            'permission_callback' => fn() => current_user_can('manage_options'),
        ]);
        
        register_rest_route('property-spotlight/v1', '/settings', [
            'methods' => 'POST',
            'callback' => [$this, 'rest_save_settings'],
            'permission_callback' => fn() => Property_Spotlight_Admin::user_can_manage_listings(),
            'args' => [
                'featured_ids' => [
                    'type'     => 'array',
                    'required' => true,
                    'items'    => [
                        'type'       => 'object',
                        'properties' => [
                            'id'      => ['type' => 'string'],
                            'added'   => ['type' => ['integer', 'null']],
                            'expires' => ['type' => ['integer', 'null']],
                            'start'   => ['type' => ['integer', 'null']],
                            'end'     => ['type' => ['integer', 'null']],
                        ],
                        'required' => ['id'],
                    ],
                ],
            ],
        ]);
    }
    
    /**
     * REST callback: Get all listings
     */
    public function rest_get_all_listings(\WP_REST_Request $request): \WP_REST_Response {
        $lang = $request->get_param('lang') ?? $this->get_current_language();
        $listings = $this->api->get_all_listings($lang);
        
        if (is_wp_error($listings)) {
            return new \WP_REST_Response(['error' => $listings->get_error_message()], 400);
        }
        
        return new \WP_REST_Response($listings, 200);
    }
    
    /**
     * REST callback: Get featured listings
     */
    public function rest_get_featured_listings(\WP_REST_Request $request): \WP_REST_Response {
        $lang = $request->get_param('lang') ?? $this->get_current_language();
        $limit = $request->get_param('limit') ?? 0;
        
        $listings = $this->api->get_featured_listings($lang, (int) $limit);
        
        if (is_wp_error($listings)) {
            return new \WP_REST_Response(['error' => $listings->get_error_message()], 400);
        }
        
        return new \WP_REST_Response($listings, 200);
    }
    
    /**
     * REST callback: Get settings
     */
    public function rest_get_settings(): \WP_REST_Response {
        $safe = $this->options;
        unset($safe['api_key'], $safe['data_url'], $safe['client_secret']);
        return new \WP_REST_Response($safe, 200);
    }
    
    /**
     * REST callback: Save settings
     */
    public function rest_save_settings(\WP_REST_Request $request): \WP_REST_Response {
        $featured_ids = $request->get_param('featured_ids');

        if (!is_array($featured_ids)) {
            return new \WP_REST_Response(['error' => 'Invalid featured_ids'], 400);
        }

        $sanitized = [];
        foreach ($featured_ids as $item) {
            if (!is_array($item) || empty($item['id'])) {
                continue;
            }
            $sanitized[] = [
                'id'      => sanitize_text_field($item['id']),
                'added'   => isset($item['added']) && null !== $item['added'] ? absint($item['added']) : time(),
                'expires' => isset($item['expires']) && null !== $item['expires'] ? absint($item['expires']) : null,
                'start'   => isset($item['start']) && null !== $item['start'] ? absint($item['start']) : null,
                'end'     => isset($item['end']) && null !== $item['end'] ? absint($item['end']) : null,
            ];
        }

        $this->options['featured_ids'] = $sanitized;
        update_option('property_spotlight_settings', $this->options);

        delete_transient('property_spotlight_featured_cache');

        return new \WP_REST_Response(['success' => true, 'featured_ids' => $sanitized], 200);
    }
    
    /**
     * Enqueue frontend assets
     */
    public function enqueue_frontend_assets(): void {
        wp_register_style(
            'property-spotlight-frontend',
            PROPERTY_SPOTLIGHT_PLUGIN_URL . 'assets/css/frontend.css',
            [],
            PROPERTY_SPOTLIGHT_VERSION
        );
        wp_register_script(
            'property-spotlight-carousel',
            PROPERTY_SPOTLIGHT_PLUGIN_URL . 'assets/js/carousel.js',
            [],
            PROPERTY_SPOTLIGHT_VERSION,
            true
        );
    }
    
    /**
     * Get current language (supports WPML, Polylang, TranslatePress)
     */
    public function get_current_language(): string {
        // Polylang
        if (function_exists('pll_current_language')) {
            return substr(pll_current_language('slug'), 0, 2);
        }
        
        // WPML
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Third-party WPML hook
        if (has_filter('wpml_current_language')) {
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Third-party WPML hook
            return substr(apply_filters('wpml_current_language', null), 0, 2);
        }
        
        // TranslatePress
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Third-party TranslatePress global
        global $TRP_LANGUAGE;
        if (!empty($TRP_LANGUAGE) && is_string($TRP_LANGUAGE)) {
            return substr($TRP_LANGUAGE, 0, 2);
        }
        
        return substr(get_locale(), 0, 2);
    }
    
    /**
     * Get plugin option
     */
    public function get_option(string $key, mixed $default = null): mixed {
        return $this->options[$key] ?? $default;
    }
    
    /**
     * Get featured listing IDs
     */
    public function get_featured_ids(): array {
        return $this->options['featured_ids'] ?? [];
    }
    
    /**
     * Get API handler
     */
    public function get_api(): Property_Spotlight_API {
        return $this->api;
    }
}
