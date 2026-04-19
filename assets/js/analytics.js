/**
 * Property Spotlight Analytics
 * 
 * Fires events for GA4 and Matomo on listing impressions and clicks.
 * Events are pushed to dataLayer for GTM/GA4 and _paq for Matomo.
 * 
 * @package Property_Spotlight
 * @since 1.1.0
 */
(function() {
    'use strict';

    // Initialize dataLayer for GA4/GTM if not exists
    window.dataLayer = window.dataLayer || [];

    /**
     * Track an event to all available analytics platforms
     * 
     * @param {string} eventName - Event name
     * @param {Object} eventData - Event parameters
     */
    function trackEvent(eventName, eventData) {
        // GA4 via gtag (direct)
        if (typeof gtag === 'function') {
            gtag('event', eventName, eventData);
        }

        // GA4 via GTM dataLayer
        window.dataLayer.push({
            event: eventName,
            ...eventData
        });

        // Matomo via _paq
        if (typeof _paq !== 'undefined') {
            _paq.push(['trackEvent', 'Property Spotlight', eventName, eventData.listing_id, eventData.position]);
        }

        // Matomo Tag Manager
        if (typeof _mtm !== 'undefined') {
            _mtm.push({
                event: eventName,
                ...eventData
            });
        }
    }

    /**
     * Track listing impression (view)
     * 
     * @param {HTMLElement} item - The listing card element
     * @param {number} position - Position in the list (1-based)
     */
    function trackImpression(item, position) {
        var listingId = item.dataset.listingId;
        var listingAddress = item.dataset.listingAddress || '';

        if (!listingId) return;

        trackEvent('property_spotlight_view', {
            listing_id: listingId,
            listing_address: listingAddress,
            position: position
        });
    }

    /**
     * Track listing click
     * 
     * @param {HTMLElement} item - The listing card element
     * @param {number} position - Position in the list (1-based)
     */
    function trackClick(item, position) {
        var listingId = item.dataset.listingId;
        var listingAddress = item.dataset.listingAddress || '';

        if (!listingId) return;

        trackEvent('property_spotlight_click', {
            listing_id: listingId,
            listing_address: listingAddress,
            position: position
        });
    }

    /**
     * Initialize tracking for all spotlight containers on the page
     */
    function initTracking() {
        var containers = document.querySelectorAll('.property-spotlight');

        containers.forEach(function(container) {
            var items = container.querySelectorAll('.property-spotlight__item');
            
            // Track impressions using IntersectionObserver
            if ('IntersectionObserver' in window) {
                var observer = new IntersectionObserver(function(entries) {
                    entries.forEach(function(entry) {
                        if (entry.isIntersecting) {
                            var item = entry.target;
                            var position = Array.from(items).indexOf(item) + 1;
                            
                            // Only track once per item
                            if (!item.dataset.tracked) {
                                trackImpression(item, position);
                                item.dataset.tracked = 'true';
                            }
                            
                            // Stop observing after first impression
                            observer.unobserve(item);
                        }
                    });
                }, {
                    threshold: 0.5 // Item must be 50% visible
                });

                items.forEach(function(item) {
                    observer.observe(item);
                });
            } else {
                // Fallback: track all immediately if IntersectionObserver not supported
                items.forEach(function(item, index) {
                    trackImpression(item, index + 1);
                });
            }

            // Track clicks
            items.forEach(function(item, index) {
                var link = item.querySelector('.property-spotlight__link');
                if (link) {
                    link.addEventListener('click', function() {
                        trackClick(item, index + 1);
                    });
                }
            });
        });
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initTracking);
    } else {
        initTracking();
    }
})();
