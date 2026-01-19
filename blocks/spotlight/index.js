/**
 * Property Spotlight Gutenberg Block
 */
(function(wp) {
    const { registerBlockType } = wp.blocks;
    const { useBlockProps, InspectorControls } = wp.blockEditor;
    const { PanelBody, RangeControl, SelectControl, Placeholder, Spinner } = wp.components;
    const { useState, useEffect } = wp.element;
    const { __ } = wp.i18n;
    const apiFetch = wp.apiFetch;

    /**
     * Block icon
     */
    const blockIcon = wp.element.createElement('svg', {
        xmlns: 'http://www.w3.org/2000/svg',
        viewBox: '0 0 24 24',
        width: 24,
        height: 24
    }, wp.element.createElement('path', {
        fill: 'currentColor',
        d: 'M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z'
    }));

    /**
     * Register the block
     */
    registerBlockType('property-spotlight/spotlight', {
        title: __('Property Spotlight', 'property-spotlight'),
        description: __('Display featured property listings', 'property-spotlight'),
        category: 'widgets',
        icon: blockIcon,
        keywords: [
            __('linear', 'property-spotlight'),
            __('spotlight', 'property-spotlight'),
            __('featured', 'property-spotlight'),
            __('listings', 'property-spotlight'),
            __('properties', 'property-spotlight'),
            __('real estate', 'property-spotlight')
        ],
        supports: {
            html: false,
            align: ['wide', 'full']
        },
        attributes: {
            limit: {
                type: 'number',
                default: 0
            },
            layout: {
                type: 'string',
                default: 'grid'
            },
            columns: {
                type: 'number',
                default: 3
            },
            hideOnSingle: {
                type: 'string',
                default: 'auto'
            }
        },

        /**
         * Edit component
         */
        edit: function(props) {
            const { attributes, setAttributes } = props;
            const { limit, layout, columns, hideOnSingle } = attributes;
            const blockProps = useBlockProps();
            
            const [listings, setListings] = useState([]);
            const [isLoading, setIsLoading] = useState(true);
            const [error, setError] = useState(null);

            // Fetch featured listings
            useEffect(function() {
                setIsLoading(true);
                setError(null);

                apiFetch({
                    path: '/property-spotlight/v1/featured' + (limit ? '?limit=' + limit : ''),
                }).then(function(data) {
                    setListings(data || []);
                    setIsLoading(false);
                }).catch(function(err) {
                    setError(err.message || __('Error loading listings', 'property-spotlight'));
                    setIsLoading(false);
                });
            }, [limit]);

            // Render listing card
            function renderCard(listing, index) {
                return wp.element.createElement('article', {
                    key: listing.id || index,
                    className: 'property-spotlight__item'
                },
                    wp.element.createElement('div', {
                        className: 'property-spotlight__image-link'
                    },
                        listing.image 
                            ? wp.element.createElement('img', {
                                className: 'property-spotlight__image',
                                src: listing.image,
                                alt: listing.address || ''
                            })
                            : wp.element.createElement('div', {
                                className: 'property-spotlight__image property-spotlight__image--placeholder'
                            })
                    ),
                    wp.element.createElement('div', {
                        className: 'property-spotlight__content'
                    },
                        wp.element.createElement('h3', {
                            className: 'property-spotlight__address'
                        }, listing.address || __('Unknown address', 'property-spotlight')),
                        listing.city && wp.element.createElement('p', {
                            className: 'property-spotlight__location'
                        }, listing.city),
                        wp.element.createElement('p', {
                            className: 'property-spotlight__details'
                        }, [
                            listing.type,
                            listing.rooms,
                            listing.area ? listing.area + ' m²' : null
                        ].filter(Boolean).join(' | ')),
                        listing.price_formatted && wp.element.createElement('p', {
                            className: 'property-spotlight__price'
                        }, listing.price_formatted)
                    )
                );
            }

            // Build class names
            var containerClasses = ['property-spotlight'];
            containerClasses.push('property-spotlight--' + layout);
            if (layout === 'grid') {
                containerClasses.push('property-spotlight--cols-' + columns);
            }

            // Inspector controls
            var inspectorControls = wp.element.createElement(InspectorControls, null,
                wp.element.createElement(PanelBody, {
                    title: __('Display Settings', 'property-spotlight'),
                    initialOpen: true
                },
                    wp.element.createElement(SelectControl, {
                        label: __('Layout', 'property-spotlight'),
                        value: layout,
                        options: [
                            { label: __('Grid', 'property-spotlight'), value: 'grid' },
                            { label: __('List', 'property-spotlight'), value: 'list' },
                            { label: __('Carousel', 'property-spotlight'), value: 'carousel' }
                        ],
                        onChange: function(value) {
                            setAttributes({ layout: value });
                        }
                    }),
                    layout === 'grid' && wp.element.createElement(RangeControl, {
                        label: __('Columns', 'property-spotlight'),
                        value: columns,
                        onChange: function(value) {
                            setAttributes({ columns: value });
                        },
                        min: 1,
                        max: 6
                    }),
                    wp.element.createElement(RangeControl, {
                        label: __('Limit', 'property-spotlight'),
                        help: __('0 = show all featured listings', 'property-spotlight'),
                        value: limit,
                        onChange: function(value) {
                            setAttributes({ limit: value });
                        },
                        min: 0,
                        max: 20
                    }),
                    wp.element.createElement(SelectControl, {
                        label: __('Hide on single listing', 'property-spotlight'),
                        help: __('Hide spotlight when viewing individual listings', 'property-spotlight'),
                        value: hideOnSingle,
                        options: [
                            { label: __('Use global setting', 'property-spotlight'), value: 'auto' },
                            { label: __('Always hide', 'property-spotlight'), value: 'true' },
                            { label: __('Never hide', 'property-spotlight'), value: 'false' }
                        ],
                        onChange: function(value) {
                            setAttributes({ hideOnSingle: value });
                        }
                    })
                )
            );

            // Loading state
            if (isLoading) {
                return wp.element.createElement('div', blockProps,
                    inspectorControls,
                    wp.element.createElement(Placeholder, {
                        icon: blockIcon,
                        label: __('Property Spotlight', 'property-spotlight')
                    },
                        wp.element.createElement(Spinner)
                    )
                );
            }

            // Error state
            if (error) {
                return wp.element.createElement('div', blockProps,
                    inspectorControls,
                    wp.element.createElement(Placeholder, {
                        icon: blockIcon,
                        label: __('Property Spotlight', 'property-spotlight'),
                        instructions: error
                    })
                );
            }

            // Empty state
            if (!listings || listings.length === 0) {
                return wp.element.createElement('div', blockProps,
                    inspectorControls,
                    wp.element.createElement(Placeholder, {
                        icon: blockIcon,
                        label: __('Property Spotlight', 'property-spotlight'),
                        instructions: __('No featured listings selected. Go to Property Spotlight settings to select listings.', 'property-spotlight')
                    })
                );
            }

            // Preview
            var displayListings = limit > 0 ? listings.slice(0, limit) : listings;
            
            return wp.element.createElement('div', blockProps,
                inspectorControls,
                wp.element.createElement('div', {
                    className: containerClasses.join(' ')
                },
                    layout === 'carousel' 
                        ? wp.element.createElement('div', {
                            className: 'property-spotlight__track'
                        }, displayListings.map(renderCard))
                        : displayListings.map(renderCard)
                )
            );
        },

        /**
         * Save returns null for dynamic block
         */
        save: function() {
            return null;
        }
    });

})(window.wp);
