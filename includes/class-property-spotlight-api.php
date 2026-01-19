<?php
/**
 * Property Spotlight API Handler
 *
 * Fetches listings from Linear API using v2 API with header-based authentication.
 *
 * @package Property_Spotlight
 */

defined('ABSPATH') || exit;

class Property_Spotlight_API {
    
    /**
     * API base URL
     */
    private string $api_url = '';
    
    /**
     * API key (including LINEAR-API-KEY prefix)
     */
    private string $api_key = '';
    
    /**
     * Cache TTL in seconds
     */
    private int $cache_ttl = 1800;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->load_credentials();
    }
    
    /**
     * Load credentials - prioritize own settings, fall back to official Linear plugin
     */
    private function load_credentials(): void {
        // Try own settings first
        $spotlight_options = get_option('property_spotlight_settings', []);
        
        if (!empty($spotlight_options['data_url']) && !empty($spotlight_options['api_key'])) {
            $this->api_url = $spotlight_options['data_url'];
            $this->api_key = $spotlight_options['api_key'];
            return;
        }
        
        // Legacy support: check for client_secret in own settings
        if (!empty($spotlight_options['data_url']) && !empty($spotlight_options['client_secret'])) {
            $this->api_url = $spotlight_options['data_url'];
            $this->api_key = $spotlight_options['client_secret'];
            return;
        }
        
        // Fall back to official Linear plugin settings
        $linear_options = get_option('linear_settings', []);
        $this->api_url = $linear_options['data_url'] ?? '';
        $this->api_key = $linear_options['client_secret'] ?? '';
    }
    
    /**
     * Check if using own credentials vs official plugin
     */
    public function is_using_own_credentials(): bool {
        $spotlight_options = get_option('property_spotlight_settings', []);
        return (!empty($spotlight_options['data_url']) && !empty($spotlight_options['api_key'])) ||
               (!empty($spotlight_options['data_url']) && !empty($spotlight_options['client_secret']));
    }
    
    /**
     * Check if API is configured
     */
    public function is_configured(): bool {
        return !empty($this->api_url) && !empty($this->api_key);
    }
    
    /**
     * Get all listings from API (v2)
     *
     * @param string $lang Language code
     * @return array|WP_Error
     */
    public function get_all_listings(string $lang = 'fi') {
        if (!$this->is_configured()) {
            return new \WP_Error('not_configured', __('API credentials not configured', 'property-spotlight'));
        }
        
        $transient_key = 'property_spotlight_all_' . md5($this->api_url . $this->api_key) . '_' . $lang;
        $cached = get_transient($transient_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        // Build API URL - support both v2 and v3 API formats
        $base_url = rtrim($this->api_url, '/');
        
        // Detect API version based on URL pattern
        if (str_contains($base_url, 'externalapi') || str_contains($base_url, 'azurecontainerapps')) {
            // v2 API - use Authorization header with LINEAR-API-KEY prefix
            // v2 requires languages[] parameter
            $url = $base_url . '/v2/listings?languages[]=' . urlencode($lang);
            $headers = [
                'Accept' => 'application/json',
                'Authorization' => $this->api_key,
            ];
        } else {
            // v3 API (legacy data.linear.fi format)
            $url = sprintf('%s/v3/listings/%s?langs=%s&env=prod', $base_url, $this->api_key, $lang);
            $headers = ['Accept' => 'application/json'];
        }
        
        $response = wp_remote_get($url, [
            'headers' => $headers,
            'timeout' => 60,
        ]);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            return new \WP_Error(
                'api_error',
                // translators: %d is the HTTP status code returned by the API
                sprintf(__('API returned status %d', 'property-spotlight'), $status_code)
            );
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        // Handle different response formats
        $raw_listings = [];
        if (isset($body['data']) && is_array($body['data'])) {
            // v3 format: { data: [...] }
            $raw_listings = $body['data'];
        } elseif (is_array($body) && !isset($body['data'])) {
            // v2 format: direct array
            $raw_listings = $body;
        }
        
        if (empty($raw_listings)) {
            return new \WP_Error('invalid_response', __('Invalid API response', 'property-spotlight'));
        }
        
        $listings = $this->normalize_listings($raw_listings, $lang);
        
        set_transient($transient_key, $listings, $this->cache_ttl);
        
        return $listings;
    }
    
    /**
     * Get featured listings only
     *
     * @param string $lang Language code
     * @param int $limit Max listings (0 = all)
     * @return array|WP_Error
     */
    public function get_featured_listings(string $lang = 'fi', int $limit = 0) {
        $all_listings = $this->get_all_listings($lang);
        
        if (is_wp_error($all_listings)) {
            return $all_listings;
        }
        
        $settings = get_option('property_spotlight_settings', []);
        $featured_ids = $settings['featured_ids'] ?? [];
        $auto_remove_sold = !empty($settings['auto_remove_sold']);
        
        if (empty($featured_ids)) {
            return [];
        }
        
        // Get active featured items (filter by schedule and expiration)
        $active_items = $this->filter_active_listings($featured_ids, $settings);
        
        // Filter and maintain order from featured_ids
        $featured = [];
        foreach ($active_items as $item) {
            $id = is_array($item) ? ($item['id'] ?? '') : $item;
            
            foreach ($all_listings as $listing) {
                if ($listing['id'] === $id) {
                    // Skip sold listings if auto_remove_sold is enabled
                    if ($auto_remove_sold && $this->is_listing_sold($listing)) {
                        continue;
                    }
                    $featured[] = $listing;
                    break;
                }
            }
        }
        
        if ($limit > 0) {
            $featured = array_slice($featured, 0, $limit);
        }
        
        return $featured;
    }
    
    /**
     * Filter active listings based on schedule and expiration
     *
     * @param array $featured_ids Featured IDs with metadata
     * @param array $settings Plugin settings
     * @return array Active featured items
     */
    public function filter_active_listings(array $featured_ids, array $settings): array {
        $now = time();
        $auto_expire_days = (int) ($settings['auto_expire_days'] ?? 0);
        $active = [];
        
        foreach ($featured_ids as $item) {
            // Handle both old format (string) and new format (array with metadata)
            if (is_string($item) || is_numeric($item)) {
                // Old format - include as-is
                $active[] = $item;
                continue;
            }
            
            if (!is_array($item) || empty($item['id'])) {
                continue;
            }
            
            // Check schedule start date (skip if not yet started)
            if (!empty($item['start']) && $item['start'] > $now) {
                continue;
            }
            
            // Check schedule end date (skip if ended)
            if (!empty($item['end']) && $item['end'] < $now) {
                continue;
            }
            
            // Check explicit expiration date
            if (!empty($item['expires']) && $item['expires'] < $now) {
                continue;
            }
            
            // Check auto-expire based on days since added
            if ($auto_expire_days > 0 && !empty($item['added'])) {
                $days_since_added = ($now - $item['added']) / 86400;
                if ($days_since_added > $auto_expire_days) {
                    continue;
                }
            }
            
            $active[] = $item;
        }
        
        return $active;
    }
    
    /**
     * Check if a listing is sold
     *
     * @param array $listing Normalized listing data
     * @return bool
     */
    private function is_listing_sold(array $listing): bool {
        $status = strtolower($listing['status'] ?? '');
        return in_array($status, ['sold', 'myyty', 'vuokrattu', 'rented'], true);
    }
    
    /**
     * Get a single listing by ID
     *
     * @param string $id Listing ID
     * @param string $lang Language code
     * @return array|null
     */
    public function get_listing(string $id, string $lang = 'fi'): ?array {
        $listings = $this->get_all_listings($lang);
        
        if (is_wp_error($listings)) {
            return null;
        }
        
        foreach ($listings as $listing) {
            if ($listing['id'] === $id) {
                return $listing;
            }
        }
        
        return null;
    }
    
    /**
     * Normalize listings data for easier use
     *
     * @param array $listings Raw listings from API
     * @param string $lang Language code for URL generation
     * @return array
     */
    private function normalize_listings(array $listings, string $lang = 'fi'): array {
        $normalized = [];
        
        // Get parent page URL for generating permalinks (same as official plugin)
        $parent_page_url = $this->get_parent_page_url($lang);
        
        foreach ($listings as $listing) {
            $non_localized = $listing['nonLocalizedValues'] ?? [];
            $localized = $listing['localizedValues'] ?? [];
            
            // Images can be at top level OR under nonLocalizedValues depending on API version
            $images = $listing['images'] ?? $non_localized['images'] ?? [];
            
            // Extract main image URL from images array
            $main_image = $this->get_main_image_url($images);
            
            // Generate URL - prefer wordPressPermalink, then generate from parent page, then fallback to API url
            $url = $this->get_listing_url($non_localized, $parent_page_url);
            
            $normalized[] = [
                'id' => $non_localized['id'] ?? '',
                'address' => $non_localized['address'] ?? '',
                'city' => $localized['city'] ?? $non_localized['city'] ?? '',
                'postal_code' => $non_localized['postalCode'] ?? '',
                'price' => $non_localized['price'] ?? 0,
                'price_formatted' => $this->format_price($non_localized['price'] ?? 0),
                'area' => $non_localized['area'] ?? 0,
                'rooms' => $non_localized['rooms'] ?? '',
                'type' => $localized['type'] ?? '',
                'status' => $non_localized['status'] ?? '',
                'image' => $main_image,
                'images' => $images,
                'url' => $url,
                'description' => $localized['description'] ?? '',
                'raw' => $listing,
            ];
        }
        
        return $normalized;
    }
    
    /**
     * Get parent page URL for listing permalinks
     * Uses official Linear plugin settings if available
     *
     * @param string $lang Language code
     * @return string
     */
    private function get_parent_page_url(string $lang): string {
        // Check official Linear plugin settings
        $linear_settings = get_option('linear_settings', []);
        $dynamic_parent_pages = $linear_settings['dynamic_parent_pages'] ?? [];
        
        if (!empty($dynamic_parent_pages[$lang])) {
            $page_id = (int) $dynamic_parent_pages[$lang];
            $url = get_permalink($page_id);
            
            if ($url) {
                // Handle TranslatePress language URLs
                if (class_exists('TRP_Translate_Press')) {
                    $trp = \TRP_Translate_Press::get_trp_instance();
                    $url_converter = $trp->get_component('url_converter');
                    $url = esc_url($url_converter->get_url_for_language($lang, $url, ''));
                }
                return rtrim($url, '/');
            }
        }
        
        return '';
    }
    
    /**
     * Get listing URL
     * Generates WordPress permalink if parent page is configured
     *
     * @param array $non_localized Non-localized listing values
     * @param string $parent_page_url Parent page URL
     * @return string
     */
    private function get_listing_url(array $non_localized, string $parent_page_url): string {
        // If API already has wordPressPermalink, use it
        if (!empty($non_localized['wordPressPermalink'])) {
            return $non_localized['wordPressPermalink'];
        }
        
        // Generate permalink using same logic as official plugin
        if ($parent_page_url) {
            $address = $non_localized['address'] ?? '';
            $id = $non_localized['id'] ?? '';
            
            if ($address && $id) {
                return $parent_page_url . '/' . sanitize_title($address) . '/' . $id;
            }
        }
        
        // Fallback to API URL or empty
        return $non_localized['url'] ?? '';
    }
    
    /**
     * Get main image URL from images array
     * Prefers compressed/thumbnail for performance, falls back to full URL
     *
     * @param array $images Images array from API
     * @return string
     */
    private function get_main_image_url(array $images): string {
        if (empty($images)) {
            return '';
        }
        
        // Find first non-floor-plan image
        foreach ($images as $image) {
            if (!empty($image['isFloorPlan']) && $image['isFloorPlan'] === true) {
                continue;
            }
            
            // Prefer compressed > thumbnail > url for better performance
            if (!empty($image['compressed'])) {
                return $image['compressed'];
            }
            if (!empty($image['thumbnail'])) {
                return $image['thumbnail'];
            }
            if (!empty($image['url'])) {
                return $image['url'];
            }
        }
        
        // Fallback: use first image regardless of type
        $first = $images[0] ?? [];
        return $first['compressed'] ?? $first['thumbnail'] ?? $first['url'] ?? '';
    }
    
    /**
     * Format price for display
     *
     * @param int|float $price
     * @return string
     */
    private function format_price($price): string {
        if (empty($price) || $price <= 0) {
            return '';
        }
        
        return number_format((float) $price, 0, ',', ' ') . ' €';
    }
    
    /**
     * Clear all caches
     */
    public function clear_cache(): void {
        global $wpdb;
        
        // Use $wpdb->prepare() with escaped LIKE pattern for security
        $like_transient = $wpdb->esc_like('_transient_property_spotlight_') . '%';
        $like_timeout = $wpdb->esc_like('_transient_timeout_property_spotlight_') . '%';
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                $like_transient
            )
        );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                $like_timeout
            )
        );
    }
}
