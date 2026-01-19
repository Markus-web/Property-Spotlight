<?php
/**
 * Property Spotlight Gutenberg Block Handler
 *
 * @package Property_Spotlight
 */

defined('ABSPATH') || exit;

class Property_Spotlight_Block {
    
    /**
     * API handler instance
     */
    private Property_Spotlight_API $api;
    
    /**
     * Constructor
     */
    public function __construct(Property_Spotlight_API $api) {
        $this->api = $api;
        
        add_action('init', [$this, 'register_block']);
        add_action('enqueue_block_editor_assets', [$this, 'enqueue_editor_assets']);
    }
    
    /**
     * Register the Gutenberg block
     */
    public function register_block(): void {
        if (!function_exists('register_block_type')) {
            return;
        }
        
        register_block_type('property-spotlight/spotlight', [
            'api_version' => 3,
            'title' => __('Property Spotlight', 'property-spotlight'),
            'description' => __('Display featured property listings', 'property-spotlight'),
            'category' => 'widgets',
            'icon' => 'star-filled',
            'keywords' => ['spotlight', 'featured', 'listings', 'properties', 'real estate'],
            'supports' => [
                'html' => false,
                'align' => ['wide', 'full'],
            ],
            'attributes' => [
                'limit' => [
                    'type' => 'number',
                    'default' => 0,
                ],
                'layout' => [
                    'type' => 'string',
                    'default' => 'grid',
                ],
                'columns' => [
                    'type' => 'number',
                    'default' => 3,
                ],
                'hideOnSingle' => [
                    'type' => 'string',
                    'default' => 'auto',
                ],
            ],
            'render_callback' => [$this, 'render_block'],
            'editor_script' => 'property-spotlight-block-editor',
            'editor_style' => 'property-spotlight-block-editor-style',
            'style' => 'property-spotlight-frontend',
        ]);
    }
    
    /**
     * Enqueue editor assets
     */
    public function enqueue_editor_assets(): void {
        wp_enqueue_script(
            'property-spotlight-block-editor',
            PROPERTY_SPOTLIGHT_PLUGIN_URL . 'blocks/spotlight/index.js',
            ['wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n', 'wp-api-fetch'],
            PROPERTY_SPOTLIGHT_VERSION,
            true
        );
        
        wp_enqueue_style(
            'property-spotlight-block-editor-style',
            PROPERTY_SPOTLIGHT_PLUGIN_URL . 'assets/css/frontend.css',
            [],
            PROPERTY_SPOTLIGHT_VERSION
        );
        
        wp_localize_script('property-spotlight-block-editor', 'propertySpotlightBlock', [
            'restUrl' => rest_url('property-spotlight/v1/'),
            'nonce' => wp_create_nonce('wp_rest'),
        ]);
    }
    
    /**
     * Render block callback
     *
     * @param array $attributes Block attributes
     * @return string
     */
    public function render_block(array $attributes): string {
        $shortcode_atts = [
            'limit' => $attributes['limit'] ?? 0,
            'layout' => $attributes['layout'] ?? 'grid',
            'columns' => $attributes['columns'] ?? 3,
            'hide_on_single' => $attributes['hideOnSingle'] ?? 'auto',
        ];
        
        $shortcode_string = '[property_spotlight';
        foreach ($shortcode_atts as $key => $value) {
            if ($value !== null && $value !== '') {
                $shortcode_string .= ' ' . $key . '="' . esc_attr($value) . '"';
            }
        }
        $shortcode_string .= ']';
        
        return do_shortcode($shortcode_string);
    }
}
