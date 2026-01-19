/**
 * Property Spotlight Admin JavaScript
 * Version: 1.0.0
 */
(function($) {
    'use strict';
    
    const config = window.propertySpotlight || {};
    let allListings = [];
    let featuredItems = normalizeFeaturedItems(config.featuredIds || []);
    
    /**
     * Normalize featured items to new metadata format
     * Handles both old format (string array) and new format (object array)
     */
    function normalizeFeaturedItems(items) {
        if (!Array.isArray(items)) return [];
        
        return items.map(function(item) {
            if (typeof item === 'string' || typeof item === 'number') {
                // Old format - convert to new
                return {
                    id: String(item),
                    added: Math.floor(Date.now() / 1000),
                    expires: null,
                    start: null,
                    end: null
                };
            }
            return item;
        });
    }
    
    /**
     * Initialize tab navigation
     */
    function initTabs() {
        const $tabNav = $('.property-spotlight-tab-nav');
        const $tabButtons = $tabNav.find('.tab-button');
        const $tabPanels = $('.property-spotlight-tab-panel');
        
        if ($tabButtons.length === 0) return;
        
        /**
         * Switch to a specific tab
         */
        function switchTab(tabId) {
            if (!$('[data-tab="' + tabId + '"]').length) return;
            
            // Update buttons
            $tabButtons.removeClass('active');
            $('[data-tab="' + tabId + '"]').addClass('active');
            
            // Update panels
            $tabPanels.removeClass('active');
            $('#tab-' + tabId).addClass('active');
            
            // Update URL without page reload
            const url = new URL(window.location.href);
            const currentPage = url.searchParams.get('page');
            
            // Always use main page (property-spotlight) for consistency
            url.searchParams.set('page', 'property-spotlight');
            
            if (tabId === 'listings') {
                // Remove tab parameter for default tab
                url.searchParams.delete('tab');
            } else {
                // Add tab parameter for other tabs
                url.searchParams.set('tab', tabId);
            }
            
            window.history.pushState({}, '', url.toString());
            
            // Save to localStorage
            try {
                localStorage.setItem('propertySpotlightActiveTab', tabId);
            } catch (e) {
                // localStorage not available
            }
        }
        
        // Handle tab button clicks
        $tabButtons.on('click', function(e) {
            e.preventDefault();
            const tabId = $(this).data('tab');
            switchTab(tabId);
        });
        
        // Determine which tab to show on page load
        // Priority: 1. URL parameter, 2. PHP activeTab, 3. localStorage, 4. default (listings)
        let initialTab = 'listings';
        
        // Check URL parameter
        const urlParams = new URLSearchParams(window.location.search);
        const urlTab = urlParams.get('tab');
        if (urlTab && $('[data-tab="' + urlTab + '"]').length) {
            initialTab = urlTab;
        }
        // Check PHP activeTab (from submenu page)
        else if (config.activeTab && $('[data-tab="' + config.activeTab + '"]').length) {
            initialTab = config.activeTab;
        }
        // Check localStorage
        else {
            try {
                const savedTab = localStorage.getItem('propertySpotlightActiveTab');
                if (savedTab && $('[data-tab="' + savedTab + '"]').length) {
                    initialTab = savedTab;
                }
            } catch (e) {
                // localStorage not available
            }
        }
        
        // Switch to initial tab if not already active
        if (!$('[data-tab="' + initialTab + '"]').hasClass('active')) {
            switchTab(initialTab);
        }
    }
    
    /**
     * Get just the IDs from featured items
     */
    function getFeaturedIds() {
        return featuredItems.map(function(item) {
            return item.id;
        });
    }
    
    /**
     * Find featured item by ID
     */
    function findFeaturedItem(id) {
        return featuredItems.find(function(item) {
            return item.id === id;
        });
    }
    
    /**
     * Initialize the admin interface
     */
    function init() {
        // Initialize tab navigation first
        initTabs();
        
        bindCredentialsEvents();
        bindStyleEvents();
        bindAutomationEvents();
        bindImportExportEvents();
        bindAccessEvents();
        
        // Initialize user selector with Select2
        if ($('#access-users').length) {
            $('#access-users').select2({
                placeholder: 'Select users...',
                allowClear: true,
                width: '100%'
            });
        }
        
        // Only initialize listing functionality if API is configured
        if ($('#listing-selector').length) {
            loadListings();
            initSelect2();
            initSortable();
            bindEvents();
        }
    }
    
    /**
     * Bind credential form events
     */
    function bindCredentialsEvents() {
        $('#save-credentials').on('click', function() {
            saveCredentials(false);
        });
        
        $('#clear-credentials').on('click', function() {
            if (confirm(config.strings.confirmClear || 'Are you sure you want to clear custom credentials?')) {
                saveCredentials(true);
            }
        });
        
        $('#clear-cache').on('click', clearCache);
    }
    
    /**
     * Clear API cache
     */
    function clearCache() {
        const $button = $('#clear-cache');
        const $status = $('#credentials-status');
        
        $button.prop('disabled', true);
        $status.text(config.strings.clearing || 'Clearing...').removeClass('success error');
        
        $.ajax({
            url: config.ajaxUrl,
            type: 'POST',
            data: {
                action: 'property_spotlight_clear_cache',
                nonce: config.nonce
            },
            success: function(response) {
                if (response.success) {
                    $status.text(response.data.message || 'Cache cleared').addClass('success');
                    setTimeout(function() {
                        window.location.reload();
                    }, 1000);
                } else {
                    $status.text(response.data.message || config.strings.error).addClass('error');
                    $button.prop('disabled', false);
                }
            },
            error: function() {
                $status.text(config.strings.error || 'Error').addClass('error');
                $button.prop('disabled', false);
            }
        });
    }
    
    /**
     * Bind style settings events
     */
    function bindStyleEvents() {
        // Sync color picker with text input
        $('input[type="color"]').on('input', function() {
            const textInput = $(this).next('input[type="text"]');
            if (textInput.length) {
                textInput.val($(this).val());
            }
        });
        
        // Sync text input with color picker
        $('.property-spotlight-style-table input[type="text"]').on('input', function() {
            const colorInput = $(this).prev('input[type="color"]');
            if (colorInput.length && /^#[0-9A-F]{6}$/i.test($(this).val())) {
                colorInput.val($(this).val());
            }
        });
        
        // Border radius slider
        $('#style-border-radius').on('input', function() {
            $('#style-border-radius-value').text($(this).val() + 'px');
        });
        
        // Style presets
        $('.style-preset').on('click', function() {
            const preset = $(this).data('preset');
            applyStylePreset(preset);
        });
        
        // Save style settings
        $('#save-style').on('click', saveStyleSettings);
    }
    
    /**
     * Bind automation settings events
     */
    function bindAutomationEvents() {
        $('#save-automation').on('click', saveAutomationSettings);
    }
    
    /**
     * Save automation settings
     */
    function saveAutomationSettings() {
        const $button = $('#save-automation');
        const $status = $('#automation-status');
        
        $button.prop('disabled', true);
        $status.text(config.strings.saving || 'Saving...').removeClass('success error');
        
        $.ajax({
            url: config.ajaxUrl,
            type: 'POST',
            data: {
                action: 'property_spotlight_save_automation',
                nonce: config.nonce,
                auto_expire_days: $('#auto-expire-days').val(),
                auto_remove_sold: $('#auto-remove-sold').is(':checked') ? 1 : 0,
                enable_analytics: $('#enable-analytics').is(':checked') ? 1 : 0,
                hide_on_single: $('#hide-on-single').is(':checked') ? 1 : 0
            },
            success: function(response) {
                if (response.success) {
                    $status.text(response.data.message || 'Saved').addClass('success');
                    setTimeout(function() {
                        $status.text('');
                    }, 3000);
                } else {
                    $status.text(response.data.message || config.strings.error).addClass('error');
                }
            },
            error: function() {
                $status.text(config.strings.error || 'Error saving').addClass('error');
            },
            complete: function() {
                $button.prop('disabled', false);
            }
        });
    }
    
    /**
     * Bind import/export events
     */
    function bindImportExportEvents() {
        $('#export-settings').on('click', exportSettings);
        $('#import-settings').on('click', function() {
            $('#import-file').click();
        });
        $('#import-file').on('change', handleImportFile);
    }
    
    /**
     * Bind access control events
     */
    function bindAccessEvents() {
        $('#save-access').on('click', saveAccessSettings);
    }
    
    /**
     * Save access control settings
     */
    function saveAccessSettings() {
        const $button = $('#save-access');
        const $status = $('#access-status');
        
        $button.prop('disabled', true);
        $status.text(config.strings.saving || 'Saving...').removeClass('success error');
        
        // Collect selected roles
        const allowedRoles = [];
        $('input[name="access_roles[]"]:checked').each(function() {
            allowedRoles.push($(this).val());
        });
        
        // Collect selected users
        const allowedUsers = $('#access-users').val() || [];
        
        $.ajax({
            url: config.ajaxUrl,
            type: 'POST',
            data: {
                action: 'property_spotlight_save_access',
                nonce: config.nonce,
                allowed_roles: allowedRoles,
                allowed_users: allowedUsers
            },
            success: function(response) {
                if (response.success) {
                    $status.text(response.data.message || 'Saved').addClass('success');
                    setTimeout(function() {
                        $status.text('');
                    }, 3000);
                } else {
                    $status.text(response.data.message || config.strings.error).addClass('error');
                }
            },
            error: function() {
                $status.text(config.strings.error || 'Error saving').addClass('error');
            },
            complete: function() {
                $button.prop('disabled', false);
            }
        });
    }
    
    /**
     * Export settings to JSON file
     */
    function exportSettings() {
        const $button = $('#export-settings');
        const $status = $('#import-export-status');
        
        $button.prop('disabled', true);
        $status.text(config.strings.exporting || 'Exporting...').removeClass('success error');
        
        $.ajax({
            url: config.ajaxUrl,
            type: 'POST',
            data: {
                action: 'property_spotlight_export',
                nonce: config.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Create and download JSON file
                    const data = response.data;
                    const json = JSON.stringify(data, null, 2);
                    const blob = new Blob([json], { type: 'application/json' });
                    const url = URL.createObjectURL(blob);
                    const link = document.createElement('a');
                    
                    const date = new Date().toISOString().slice(0, 10);
                    link.href = url;
                    link.download = 'property-spotlight-settings-' + date + '.json';
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    URL.revokeObjectURL(url);
                    
                    $status.text(config.strings.exported || 'Settings exported').addClass('success');
                    setTimeout(function() {
                        $status.text('');
                    }, 3000);
                } else {
                    $status.text(response.data.message || config.strings.error).addClass('error');
                }
            },
            error: function() {
                $status.text(config.strings.error || 'Error exporting').addClass('error');
            },
            complete: function() {
                $button.prop('disabled', false);
            }
        });
    }
    
    /**
     * Handle import file selection
     */
    function handleImportFile(e) {
        const file = e.target.files[0];
        const $status = $('#import-export-status');
        const $filename = $('#import-filename');
        
        if (!file) {
            return;
        }
        
        $filename.text(file.name);
        
        const reader = new FileReader();
        reader.onload = function(e) {
            importSettings(e.target.result);
        };
        reader.onerror = function() {
            $status.text(config.strings.error || 'Error reading file').addClass('error');
        };
        reader.readAsText(file);
    }
    
    /**
     * Import settings from JSON data
     */
    function importSettings(jsonData) {
        const $button = $('#import-settings');
        const $status = $('#import-export-status');
        
        $button.prop('disabled', true);
        $status.text(config.strings.importing || 'Importing...').removeClass('success error');
        
        $.ajax({
            url: config.ajaxUrl,
            type: 'POST',
            data: {
                action: 'property_spotlight_import',
                nonce: config.nonce,
                import_data: jsonData
            },
            success: function(response) {
                if (response.success) {
                    $status.text(response.data.message || 'Settings imported').addClass('success');
                    setTimeout(function() {
                        window.location.reload();
                    }, 1500);
                } else {
                    $status.text(response.data.message || config.strings.error).addClass('error');
                    $button.prop('disabled', false);
                }
            },
            error: function() {
                $status.text(config.strings.error || 'Error importing').addClass('error');
                $button.prop('disabled', false);
            }
        });
        
        // Reset file input
        $('#import-file').val('');
        $('#import-filename').text('');
    }
    
    /**
     * Apply style preset
     */
    function applyStylePreset(preset) {
        const presets = {
            'kiinteistokolmio': {
                primary_color: '#012f75',
                accent_color: '#c54b4b',
                price_color: '#012f75',
                featured_bg: '#012f75',
                border_radius: 4
            },
            'oikotie': {
                primary_color: '#1a1a1a',
                accent_color: '#0066cc',
                price_color: '#1a1a1a',
                featured_bg: '#0066cc',
                border_radius: 12
            },
            'nettiauto': {
                primary_color: '#1a1a1a',
                accent_color: '#e65100',
                price_color: '#e65100',
                featured_bg: '#c62828',
                border_radius: 4
            },
            'minimal': {
                primary_color: '#333333',
                accent_color: '#666666',
                price_color: '#333333',
                featured_bg: '#333333',
                border_radius: 0
            },
            'default': {
                primary_color: '#1a1a1a',
                accent_color: '#0066cc',
                price_color: '#1a1a1a',
                featured_bg: '#c62828',
                border_radius: 12
            }
        };
        
        const values = presets[preset] || presets['default'];
        
        $('#style-primary-color').val(values.primary_color);
        $('#style-primary-color-text').val(values.primary_color);
        $('#style-accent-color').val(values.accent_color);
        $('#style-accent-color-text').val(values.accent_color);
        $('#style-price-color').val(values.price_color);
        $('#style-price-color-text').val(values.price_color);
        $('#style-featured-bg').val(values.featured_bg);
        $('#style-featured-bg-text').val(values.featured_bg);
        $('#style-border-radius').val(values.border_radius);
        $('#style-border-radius-value').text(values.border_radius + 'px');
    }
    
    /**
     * Save style settings
     */
    function saveStyleSettings() {
        const $button = $('#save-style');
        const $status = $('#style-status');
        
        $button.prop('disabled', true);
        $status.text(config.strings.saving || 'Saving...').removeClass('success error');
        
        $.ajax({
            url: config.ajaxUrl,
            type: 'POST',
            data: {
                action: 'property_spotlight_save_style',
                nonce: config.nonce,
                primary_color: $('#style-primary-color').val(),
                accent_color: $('#style-accent-color').val(),
                price_color: $('#style-price-color').val(),
                featured_bg: $('#style-featured-bg').val(),
                border_radius: $('#style-border-radius').val()
            },
            success: function(response) {
                if (response.success) {
                    $status.text(response.data.message || 'Saved').addClass('success');
                    setTimeout(function() {
                        $status.text('');
                    }, 3000);
                } else {
                    $status.text(response.data.message || config.strings.error).addClass('error');
                }
            },
            error: function() {
                $status.text(config.strings.error || 'Error saving').addClass('error');
            },
            complete: function() {
                $button.prop('disabled', false);
            }
        });
    }
    
    /**
     * Save API credentials
     */
    function saveCredentials(clear) {
        const $button = $('#save-credentials');
        const $status = $('#credentials-status');
        
        $button.prop('disabled', true);
        $status.text(config.strings.saving || 'Saving...').removeClass('success error');
        
        const data = {
            action: 'property_spotlight_save_credentials',
            nonce: config.nonce,
            data_url: clear ? '' : $('#data-url').val().trim(),
            api_key: clear ? '' : $('#api-key').val().trim()
        };
        
        $.ajax({
            url: config.ajaxUrl,
            type: 'POST',
            data: data,
            success: function(response) {
                if (response.success) {
                    $status.text(response.data.message || 'Saved').addClass('success');
                    setTimeout(function() {
                        // Reload page to reflect new state
                        window.location.reload();
                    }, 1000);
                } else {
                    $status.text(response.data.message || config.strings.error).addClass('error');
                    $button.prop('disabled', false);
                }
            },
            error: function() {
                $status.text(config.strings.error || 'Error saving').addClass('error');
                $button.prop('disabled', false);
            }
        });
    }
    
    /**
     * Load all listings from API
     */
    function loadListings() {
        $.ajax({
            url: config.ajaxUrl,
            type: 'GET',
            data: {
                action: 'property_spotlight_get_listings',
                nonce: config.nonce,
                lang: 'fi'
            },
            beforeSend: function() {
                $('#featured-listings').html(
                    '<div class="property-spotlight-loading">' + config.strings.loading + '</div>'
                );
            },
            success: function(response) {
                if (response.success) {
                    allListings = response.data;
                    renderFeaturedListings();
                    updateSelect2Options();
                } else {
                    showError(response.data.message || config.strings.error);
                }
            },
            error: function() {
                showError(config.strings.error);
            }
        });
    }
    
    /**
     * Initialize Select2 dropdown
     */
    function initSelect2() {
        $('#listing-selector').select2({
            placeholder: config.strings.searchPlaceholder,
            allowClear: true,
            templateResult: formatListingOption,
            templateSelection: formatListingSelection,
            matcher: customMatcher
        });
        
        $('#listing-selector').on('select2:select', function(e) {
            const id = e.params.data.id;
            if (id && !getFeaturedIds().includes(id)) {
                featuredItems.push({
                    id: id,
                    added: Math.floor(Date.now() / 1000),
                    expires: null,
                    start: null,
                    end: null
                });
                renderFeaturedListings();
                updateSelect2Options();
            }
            $(this).val(null).trigger('change');
        });
    }
    
    /**
     * Custom matcher for Select2 search
     */
    function customMatcher(params, data) {
        if ($.trim(params.term) === '') {
            return data;
        }
        
        if (typeof data.text === 'undefined') {
            return null;
        }
        
        const term = params.term.toLowerCase();
        const listing = data.listing;
        
        if (listing) {
            const searchText = [
                listing.address,
                listing.city,
                listing.id,
                listing.postal_code
            ].join(' ').toLowerCase();
            
            if (searchText.indexOf(term) > -1) {
                return data;
            }
        }
        
        return null;
    }
    
    /**
     * Format listing option in dropdown
     */
    function formatListingOption(listing) {
        if (!listing.id) {
            return listing.text;
        }
        
        const data = listing.listing;
        if (!data) {
            return listing.text;
        }
        
        const $option = $(
            '<div class="select2-results__option--listing">' +
                '<span class="listing-address">' + escapeHtml(data.address) + '</span>' +
                '<span class="listing-details">' +
                    escapeHtml(data.city) + ' | ' + 
                    escapeHtml(data.price_formatted || '-') + ' | ' +
                    escapeHtml(data.rooms || '-') +
                '</span>' +
            '</div>'
        );
        
        return $option;
    }
    
    /**
     * Format selected listing
     */
    function formatListingSelection(listing) {
        if (!listing.id) {
            return listing.text;
        }
        
        const data = listing.listing;
        if (!data) {
            return listing.id;
        }
        
        return data.address + ' (' + data.city + ')';
    }
    
    /**
     * Update Select2 options based on available listings
     */
    function updateSelect2Options() {
        const $select = $('#listing-selector');
        const ids = getFeaturedIds();
        $select.empty();
        $select.append('<option value="">' + config.strings.searchPlaceholder + '</option>');
        
        allListings.forEach(function(listing) {
            if (!ids.includes(listing.id)) {
                const option = new Option(listing.address, listing.id, false, false);
                option.listing = listing;
                $select.append(option);
            }
        });
        
        $select.trigger('change');
    }
    
    /**
     * Initialize sortable list
     */
    function initSortable() {
        $('#featured-listings').sortable({
            handle: '.drag-handle',
            placeholder: 'featured-item ui-sortable-placeholder',
            update: function() {
                updateFeaturedOrder();
            }
        });
    }
    
    /**
     * Bind event handlers
     */
    function bindEvents() {
        // Add manual ID
        $('#add-manual').on('click', function() {
            const id = $('#manual-id').val().trim();
            if (id && !getFeaturedIds().includes(id)) {
                featuredItems.push({
                    id: id,
                    added: Math.floor(Date.now() / 1000),
                    expires: null,
                    start: null,
                    end: null
                });
                renderFeaturedListings();
                updateSelect2Options();
                $('#manual-id').val('');
            }
        });
        
        // Enter key on manual input
        $('#manual-id').on('keypress', function(e) {
            if (e.which === 13) {
                e.preventDefault();
                $('#add-manual').click();
            }
        });
        
        // Remove item
        $(document).on('click', '.featured-item .remove-item', function() {
            const id = $(this).closest('.featured-item').data('id');
            featuredItems = featuredItems.filter(function(item) {
                return item.id !== id;
            });
            renderFeaturedListings();
            updateSelect2Options();
        });
        
        // Date picker changes
        $(document).on('change', '.featured-item input[type="date"]', function() {
            const $item = $(this).closest('.featured-item');
            const id = $item.data('id');
            const field = $(this).data('field');
            const value = $(this).val();
            
            // Update the item's metadata
            const item = findFeaturedItem(id);
            if (item && field) {
                item[field] = value ? Math.floor(new Date(value).getTime() / 1000) : null;
                updateItemStatus($item, item);
            }
        });
        
        // Save button
        $('#save-featured').on('click', saveFeatured);
    }
    
    /**
     * Update item status indicator
     */
    function updateItemStatus($item, itemData) {
        const now = Math.floor(Date.now() / 1000);
        let status = 'active';
        let statusText = config.strings.statusActive || 'Active';
        
        if (itemData.start && itemData.start > now) {
            status = 'scheduled';
            statusText = config.strings.statusScheduled || 'Scheduled';
        } else if (itemData.end && itemData.end < now) {
            status = 'expired';
            statusText = config.strings.statusExpired || 'Expired';
        } else if (itemData.expires && itemData.expires < now) {
            status = 'expired';
            statusText = config.strings.statusExpired || 'Expired';
        }
        
        $item.find('.item-status')
            .removeClass('status-active status-scheduled status-expired')
            .addClass('status-' + status)
            .text(statusText);
    }
    
    /**
     * Update featured order from DOM
     */
    function updateFeaturedOrder() {
        const newOrder = [];
        $('#featured-listings .featured-item').each(function() {
            const id = $(this).data('id');
            const item = findFeaturedItem(id);
            if (item) {
                newOrder.push(item);
            }
        });
        featuredItems = newOrder;
    }
    
    /**
     * Render featured listings
     */
    function renderFeaturedListings() {
        const $container = $('#featured-listings');
        
        if (featuredItems.length === 0) {
            $container.removeClass('has-items').html(
                '<div class="no-featured">' +
                    'No featured listings selected. Use the options above to add listings.' +
                '</div>'
            );
            return;
        }
        
        $container.addClass('has-items').empty();
        
        featuredItems.forEach(function(item) {
            const listing = findListing(item.id);
            $container.append(createListingItem(item, listing));
        });
    }
    
    /**
     * Find listing by ID
     */
    function findListing(id) {
        return allListings.find(function(l) {
            return l.id === id;
        });
    }
    
    /**
     * Create listing item HTML
     */
    function createListingItem(item, listing) {
        const id = item.id;
        const address = listing ? escapeHtml(listing.address) : 'Unknown listing';
        const city = listing ? escapeHtml(listing.city) : '';
        const price = listing ? escapeHtml(listing.price_formatted || '') : '';
        const image = listing && listing.image ? listing.image : '';
        
        // Calculate status
        const now = Math.floor(Date.now() / 1000);
        let status = 'active';
        let statusText = config.strings.statusActive || 'Active';
        
        if (item.start && item.start > now) {
            status = 'scheduled';
            statusText = config.strings.statusScheduled || 'Scheduled';
        } else if (item.end && item.end < now) {
            status = 'expired';
            statusText = config.strings.statusExpired || 'Expired';
        }
        
        // Format dates for inputs (YYYY-MM-DD for HTML input) and display (d.m.Y for Finnish)
        const startDate = item.start ? formatDateForInput(item.start) : '';
        const endDate = item.end ? formatDateForInput(item.end) : '';
        const startDateDisplay = item.start ? formatDateForDisplay(item.start) : '';
        const endDateDisplay = item.end ? formatDateForDisplay(item.end) : '';
        
        let imageHtml = '<div class="listing-image" style="background:#ddd;"></div>';
        if (image) {
            imageHtml = '<img class="listing-image" src="' + escapeHtml(image) + '" alt="">';
        }
        
        return $(
            '<div class="featured-item" data-id="' + escapeHtml(id) + '">' +
                '<span class="drag-handle dashicons dashicons-menu"></span>' +
                imageHtml +
                '<div class="listing-info">' +
                    '<div class="listing-address">' + address + '</div>' +
                    '<div class="listing-meta">' +
                        (city ? city + ' | ' : '') +
                        (price ? price : '') +
                    '</div>' +
                '</div>' +
                '<div class="listing-schedule">' +
                    '<div class="schedule-row">' +
                        '<label>' + (config.strings.startDate || 'Start') + '</label>' +
                        '<input type="date" data-field="start" value="' + startDate + '">' +
                        (startDateDisplay ? '<span class="date-display">' + startDateDisplay + '</span>' : '') +
                    '</div>' +
                    '<div class="schedule-row">' +
                        '<label>' + (config.strings.endDate || 'End') + '</label>' +
                        '<input type="date" data-field="end" value="' + endDate + '">' +
                        (endDateDisplay ? '<span class="date-display">' + endDateDisplay + '</span>' : '') +
                    '</div>' +
                    '<span class="item-status status-' + status + '">' + statusText + '</span>' +
                '</div>' +
                '<span class="listing-id">' + escapeHtml(id) + '</span>' +
                '<button type="button" class="remove-item" title="Remove">' +
                    '<span class="dashicons dashicons-no-alt"></span>' +
                '</button>' +
            '</div>'
        );
    }
    
    /**
     * Format timestamp for HTML date input (YYYY-MM-DD required by browsers)
     */
    function formatDateForInput(timestamp) {
        if (!timestamp) return '';
        const date = new Date(timestamp * 1000);
        return date.toISOString().split('T')[0];
    }
    
    /**
     * Format timestamp for Finnish date display (d.m.Y)
     */
    function formatDateForDisplay(timestamp) {
        if (!timestamp) return '';
        const date = new Date(timestamp * 1000);
        const day = String(date.getDate()).padStart(2, '0');
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const year = date.getFullYear();
        return day + '.' + month + '.' + year;
    }
    
    /**
     * Save featured listings
     */
    function saveFeatured() {
        const $button = $('#save-featured');
        const $status = $('#save-status');
        
        $button.prop('disabled', true);
        $status.text(config.strings.saving).removeClass('success error');
        
        $.ajax({
            url: config.ajaxUrl,
            type: 'POST',
            data: {
                action: 'property_spotlight_save',
                nonce: config.nonce,
                featured_ids: JSON.stringify(featuredItems)
            },
            success: function(response) {
                if (response.success) {
                    $status.text(config.strings.saved).addClass('success');
                    setTimeout(function() {
                        $status.text('');
                    }, 3000);
                } else {
                    $status.text(response.data.message || config.strings.error).addClass('error');
                }
            },
            error: function() {
                $status.text(config.strings.error).addClass('error');
            },
            complete: function() {
                $button.prop('disabled', false);
            }
        });
    }
    
    /**
     * Show error message
     */
    function showError(message) {
        $('#featured-listings').html(
            '<div class="notice notice-error" style="margin:0;padding:12px;">' +
                '<p>' + escapeHtml(message) + '</p>' +
            '</div>'
        );
    }
    
    /**
     * Escape HTML
     */
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // Initialize on document ready
    $(document).ready(init);
    
})(jQuery);
