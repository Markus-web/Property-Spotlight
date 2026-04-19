<?php
/**
 * Property Spotlight Shortcode Handler
 *
 * Inspired by Nettiauto.com design patterns:
 * - "NÄYTEIKKUNA" (Featured) - Colored cards with overlay text
 * - "NÄYTEIKKUNA" (Showcase) - Clean white cards
 *
 * @package Property_Spotlight
 */

defined('ABSPATH') || exit;

class Property_Spotlight_Shortcode {
    
    /**
     * API handler instance
     */
    private Property_Spotlight_API $api;
    
    /**
     * Constructor
     */
    public function __construct(Property_Spotlight_API $api) {
        $this->api = $api;
        
        add_shortcode('property_spotlight', [$this, 'render_shortcode']);
    }
    
    /**
     * Render the shortcode
     *
     * @param array|string|null $atts Shortcode attributes (WordPress may pass null or string)
     * @return string
     */
    public function render_shortcode(array|string|null $atts = null): string {
        // WordPress can pass null, string, or array - normalize to array
        if (!is_array($atts)) {
            $atts = [];
        }
        $atts = shortcode_atts([
            'limit' => 0,
            'layout' => 'grid',
            'columns' => 3,
            'style' => 'default',
            'lang' => '',
            'class' => '',
            'title' => '',
            'show_price' => 'true',
            'show_address' => 'true',
            'show_image' => 'true',
            'show_location' => 'true',
            'show_details' => 'true',
            'show_area' => 'true',
            'hide_on_single' => 'auto',
        ], $atts, 'property_spotlight');
        
        // Check if we should hide on single listing view
        $hide_on_single = $atts['hide_on_single'];
        if ($hide_on_single === 'auto') {
            // Use global setting
            $settings = get_option('property_spotlight_settings', []);
            $hide_on_single = !empty($settings['hide_on_single']);
        } else {
            $hide_on_single = filter_var($hide_on_single, FILTER_VALIDATE_BOOLEAN);
        }
        
        if ($hide_on_single && $this->is_single_listing_view()) {
            return '';
        }
        
        // Validate layout
        $valid_layouts = ['grid', 'list', 'carousel'];
        if (!in_array($atts['layout'], $valid_layouts)) {
            $atts['layout'] = 'grid';
        }
        
        // Validate style
        $valid_styles = ['default', 'featured', 'compact', 'dark'];
        if (!in_array($atts['style'], $valid_styles)) {
            $atts['style'] = 'default';
        }
        
        // Validate columns
        $atts['columns'] = max(1, min(6, (int) $atts['columns']));
        
        // Convert string booleans to actual booleans
        $atts['show_price'] = filter_var($atts['show_price'], FILTER_VALIDATE_BOOLEAN);
        $atts['show_address'] = filter_var($atts['show_address'], FILTER_VALIDATE_BOOLEAN);
        $atts['show_image'] = filter_var($atts['show_image'], FILTER_VALIDATE_BOOLEAN);
        $atts['show_location'] = filter_var($atts['show_location'], FILTER_VALIDATE_BOOLEAN);
        $atts['show_details'] = filter_var($atts['show_details'], FILTER_VALIDATE_BOOLEAN);
        $atts['show_area'] = filter_var($atts['show_area'], FILTER_VALIDATE_BOOLEAN);
        
        // Get language
        $lang = $atts['lang'] ?: Property_Spotlight::get_instance()->get_current_language();
        
        // Get featured listings
        $listings = $this->api->get_featured_listings($lang, (int) $atts['limit']);
        
        if (is_wp_error($listings) || empty($listings)) {
            return '';
        }
        
        // Enqueue styles
        wp_enqueue_style('property-spotlight-frontend');
        
        // Enqueue analytics script if enabled
        $settings = get_option('property_spotlight_settings', []);
        if (!empty($settings['enable_analytics'])) {
            wp_enqueue_script(
                'property-spotlight-analytics',
                PROPERTY_SPOTLIGHT_PLUGIN_URL . 'assets/js/analytics.js',
                [],
                PROPERTY_SPOTLIGHT_VERSION,
                true
            );
        }
        
        // Build output
        return $this->render_listings($listings, $atts);
    }
    
    /**
     * Get custom CSS variables from settings
     *
     * @return string
     */
    private function get_custom_styles(): string {
        $settings = get_option('property_spotlight_settings', []);
        $style = $settings['style'] ?? [];
        
        $vars = [];
        
        if (!empty($style['primary_color'])) {
            $vars[] = '--property-spotlight-primary: ' . esc_attr($style['primary_color']);
        }
        if (!empty($style['accent_color'])) {
            $vars[] = '--property-spotlight-accent: ' . esc_attr($style['accent_color']);
        }
        if (!empty($style['price_color'])) {
            $vars[] = '--property-spotlight-price: ' . esc_attr($style['price_color']);
        }
        if (!empty($style['featured_bg'])) {
            $vars[] = '--property-spotlight-featured-bg: ' . esc_attr($style['featured_bg']);
        }
        if (isset($style['border_radius'])) {
            $vars[] = '--property-spotlight-radius: ' . absint($style['border_radius']) . 'px';
        }
        
        return !empty($vars) ? implode('; ', $vars) : '';
    }
    
    /**
     * Render listings HTML
     *
     * @param array $listings
     * @param array $atts
     * @return string
     */
    private function render_listings(array $listings, array $atts): string {
        $layout = $atts['layout'];
        $style = $atts['style'];
        $columns = $atts['columns'];
        $custom_class = sanitize_html_class($atts['class']);
        $title = $atts['title'];
        
        $classes = [
            'property-spotlight',
            'property-spotlight--' . $layout,
        ];
        
        // Add style modifier
        if ($style !== 'default') {
            $classes[] = 'property-spotlight--' . $style;
        }
        
        if ($layout === 'grid') {
            $classes[] = 'property-spotlight--cols-' . $columns;
        }
        
        if ($custom_class) {
            $classes[] = $custom_class;
        }
        
        // Get custom CSS variables
        $custom_styles = $this->get_custom_styles();
        $style_attr = $custom_styles ? ' style="' . esc_attr($custom_styles) . '"' : '';
        
        $output = '';
        
        // Section header with title
        if ($title) {
            $output .= '<div class="property-spotlight__header">';
            $output .= '<h2 class="property-spotlight__title">';
            if ($style === 'featured') {
                $output .= '<svg class="property-spotlight__title-icon" viewBox="0 0 24 24"><path fill="currentColor" d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>';
            }
            $output .= esc_html($title);
            $output .= '</h2>';
            $output .= '</div>';
        }
        
        $output .= '<div class="' . esc_attr(implode(' ', $classes)) . '"' . $style_attr . '>';
        
        if ($layout === 'carousel') {
            $output .= '<div class="property-spotlight__track">';
        }
        
        foreach ($listings as $listing) {
            $output .= $this->render_listing_card($listing, $atts);
        }
        
        if ($layout === 'carousel') {
            $output .= '</div>';
            $output .= $this->render_carousel_controls();
        }
        
        $output .= '</div>';
        
        if ($layout === 'carousel') {
            $output .= $this->get_carousel_script();
        }
        
        return $output;
    }
    
    /**
     * Render individual listing card (Oikotie-inspired design)
     *
     * Layout: Image → Address, City → Price • Area → Rooms • Type, Year
     *
     * @param array $listing
     * @param array $atts Shortcode attributes
     * @return string
     */
    private function render_listing_card(array $listing, array $atts): string {
        $url = $listing['url'] ?: '#';
        $image = $listing['image'] ?: '';
        $address = $listing['address'];
        $city = $listing['city'];
        $price = $listing['price_formatted'];
        $area = $listing['area'];
        $rooms = $listing['rooms'];
        $type = $listing['type'];
        
        // Get display options
        $show_image = $atts['show_image'] ?? true;
        $show_address = $atts['show_address'] ?? true;
        $show_location = $atts['show_location'] ?? true;
        $show_details = $atts['show_details'] ?? true;
        $show_price = $atts['show_price'] ?? true;
        $show_area = $atts['show_area'] ?? true;
        $style = $atts['style'] ?? 'default';
        
        $listing_id = $listing['id'] ?? '';
        
        $output = '<article class="property-spotlight__item" data-listing-id="' . esc_attr($listing_id) . '" data-listing-address="' . esc_attr($address) . '">';
        $output .= '<a href="' . esc_url($url) . '" class="property-spotlight__link">';
        
        // Image
        if ($show_image) {
            $output .= '<div class="property-spotlight__image-wrapper">';
            if ($image) {
                $output .= '<img src="' . esc_url($image) . '" alt="' . esc_attr($address) . '" class="property-spotlight__image" loading="lazy">';
            } else {
                $output .= '<div class="property-spotlight__image property-spotlight__image--placeholder"></div>';
            }
            $output .= '</div>';
        }
        
        // Content area
        $output .= '<div class="property-spotlight__content">';
        
        // Address with City (Oikotie style: "Katuosoite 1, Kaupunginosa, Kaupunki")
        if ($show_address) {
            $full_address = $address;
            if ($show_location && $city) {
                $full_address .= ', ' . $city;
            }
            $output .= '<div class="property-spotlight__address">' . esc_html($full_address) . '</div>';
        }
        
        // Row 1: Price • Area (Oikotie style)
        $row1 = [];
        if ($show_price && $price) {
            $row1[] = '<span class="property-spotlight__price">' . esc_html($price) . '</span>';
        }
        if ($show_area && $area) {
            $row1[] = '<span class="property-spotlight__area">' . esc_html($area) . ' m²</span>';
        }
        if (!empty($row1)) {
            $output .= '<div class="property-spotlight__row">' . implode('<span class="property-spotlight__sep">•</span>', $row1) . '</div>';
        }
        
        // Row 2: Rooms • Type, Year (Oikotie style)
        if ($show_details && $style !== 'featured') {
            $row2 = [];
            if ($rooms) {
                $row2[] = '<span class="property-spotlight__rooms">' . esc_html($rooms) . '</span>';
            }
            if ($type) {
                $row2[] = '<span class="property-spotlight__type">' . esc_html($type) . '</span>';
            }
            if (!empty($row2)) {
                $output .= '<div class="property-spotlight__row property-spotlight__row--meta">' . implode('<span class="property-spotlight__sep">•</span>', $row2) . '</div>';
            }
        }
        
        $output .= '</div>'; // content
        $output .= '</a>';
        $output .= '</article>';
        
        return $output;
    }
    
    /**
     * Render carousel controls
     *
     * @return string
     */
    private function render_carousel_controls(): string {
        return '
            <button type="button" class="property-spotlight__nav property-spotlight__nav--prev" aria-label="' . esc_attr__('Previous', 'property-spotlight') . '">
                <svg viewBox="0 0 24 24" width="24" height="24"><path fill="currentColor" d="M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12z"/></svg>
            </button>
            <button type="button" class="property-spotlight__nav property-spotlight__nav--next" aria-label="' . esc_attr__('Next', 'property-spotlight') . '">
                <svg viewBox="0 0 24 24" width="24" height="24"><path fill="currentColor" d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"/></svg>
            </button>
        ';
    }
    
    /**
     * Get carousel JavaScript
     *
     * @return string
     */
    private function get_carousel_script(): string {
        return '
        <script>
        (function() {
            document.querySelectorAll(".property-spotlight--carousel").forEach(function(carousel) {
                var track = carousel.querySelector(".property-spotlight__track");
                var prevBtn = carousel.querySelector(".property-spotlight__nav--prev");
                var nextBtn = carousel.querySelector(".property-spotlight__nav--next");
                var items = carousel.querySelectorAll(".property-spotlight__item");
                
                if (!track || !items.length) return;
                
                var currentIndex = 0;
                var itemWidth = items[0].offsetWidth;
                var gap = 16;
                var visibleItems = Math.floor(carousel.offsetWidth / (itemWidth + gap)) || 1;
                var maxIndex = Math.max(0, items.length - visibleItems);
                
                function updatePosition() {
                    track.style.transform = "translateX(-" + (currentIndex * (itemWidth + gap)) + "px)";
                    if (prevBtn) prevBtn.disabled = currentIndex <= 0;
                    if (nextBtn) nextBtn.disabled = currentIndex >= maxIndex;
                }
                
                if (prevBtn) {
                    prevBtn.addEventListener("click", function() {
                        if (currentIndex > 0) {
                            currentIndex--;
                            updatePosition();
                        }
                    });
                }
                
                if (nextBtn) {
                    nextBtn.addEventListener("click", function() {
                        if (currentIndex < maxIndex) {
                            currentIndex++;
                            updatePosition();
                        }
                    });
                }
                
                window.addEventListener("resize", function() {
                    itemWidth = items[0].offsetWidth;
                    visibleItems = Math.floor(carousel.offsetWidth / (itemWidth + gap)) || 1;
                    maxIndex = Math.max(0, items.length - visibleItems);
                    if (currentIndex > maxIndex) currentIndex = maxIndex;
                    updatePosition();
                });
                
                updatePosition();
            });
        })();
        </script>
        ';
    }
    
    /**
     * Check if current page is viewing a single listing
     *
     * Detects single listing view by checking if URL contains address/id segments
     * beyond the listing parent page URL.
     *
     * URL pattern: {parent-page-url}/{address-slug}/{listing-id}
     *
     * @return bool
     */
    private function is_single_listing_view(): bool {
        $linear_settings = get_option('linear_settings', []);
        $dynamic_parent_pages = $linear_settings['dynamic_parent_pages'] ?? [];
        
        if (empty($dynamic_parent_pages)) {
            return false;
        }
        
        // Get current request URI
        $current_url = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '';
        if (empty($current_url)) {
            return false;
        }
        
        $current_url = trailingslashit(strtok($current_url, '?')); // Remove query string and ensure trailing slash
        
        foreach ($dynamic_parent_pages as $page_id) {
            $parent_url = get_permalink((int) $page_id);
            if (!$parent_url) {
                continue;
            }
            
            $parent_path = wp_parse_url($parent_url, PHP_URL_PATH);
            if (!$parent_path) {
                continue;
            }
            
            $parent_path = trailingslashit($parent_path);
            
            // Check if current URL starts with parent path
            if (strpos($current_url, $parent_path) === 0) {
                // Get the remaining path after parent
                $remaining = substr($current_url, strlen($parent_path));
                $remaining = trim($remaining, '/');
                
                // Single listing has two segments: address-slug/listing-id
                if (!empty($remaining) && preg_match('#^[^/]+/[^/]+$#', $remaining)) {
                    return true;
                }
            }
        }
        
        return false;
    }
}
