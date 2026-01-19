=== Property Spotlight ===
Contributors: markusmedia
Tags: real estate, property, listings, linear, spotlight
Requires at least: 6.4
Tested up to: 6.9
Stable tag: 1.2.1
Requires PHP: 8.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Feature specific property listings on your WordPress website with manual selection controls.

== Description ==

Property Spotlight allows you to manually select and feature specific property listings from the Linear.fi real estate platform on your WordPress website.

**Key Features:**

* Manual selection of featured listings via searchable dropdown
* Drag-and-drop reordering of featured properties
* Multiple display layouts: grid, list, carousel
* Multiple card styles: default, featured, compact, dark
* Gutenberg block with live preview
* Shortcode with extensive attributes
* Style customization with color pickers and presets
* Analytics tracking (GA4, GTM, Matomo) - opt-in
* Scheduled featuring with start/end dates
* Auto-expiration after configurable days
* Automatic sold/rented listing filtering
* Import/Export settings
* Finnish translations included (330+ strings)
* Hide spotlight on single listing pages option

**API Integration:**

Works with Linear.fi API. You can either:

1. Enter your own API credentials (standalone mode)
2. Use the official Linear plugin's credentials automatically

**Shortcode Usage:**

`[property_spotlight]`

`[property_spotlight style="featured" title="NÄYTEIKKUNA" limit="3"]`

`[property_spotlight layout="carousel" columns="4"]`

**Gutenberg Block:**

Search for "Property Spotlight" (or "Näyteikkuna" in Finnish) in the block inserter.

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/property-spotlight/`
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Navigate to **Property Spotlight** in the admin menu
4. Enter your Linear.fi API credentials or use the official Linear plugin
5. Select listings to feature using the searchable dropdown
6. Add the shortcode or Gutenberg block to your pages

== Frequently Asked Questions ==

= Do I need a Linear.fi account? =

Yes, you need Linear.fi API credentials to fetch property listings. The plugin supports both standalone credentials and integration with the official Linear plugin.

= Can I use test credentials? =

Yes, Linear.fi provides public test API credentials for development:

* Data URL: `https://ca-externalapi-test-weu-001.livelyrock-4a193af6.westeurope.azurecontainerapps.io`
* API Key: `LINEAR-API-KEY b6b23692-e3fa-4525-9709-e021c8a65baa`

= What layouts are available? =

Grid (default), List, and Carousel layouts are available via shortcode attributes or block settings.

= What card styles are available? =

Default, Featured (with colored header), Compact (minimal), and Dark styles.

= Does the plugin track users? =

Analytics tracking is disabled by default. You can enable it in Settings to track impressions and clicks via GA4, GTM, or Matomo.

= How do I hide the spotlight on single listing pages? =

Use `hide_on_single="auto"` (default) to automatically hide on pages matching the Linear plugin's listing URL pattern, or set to `"true"` to always hide on singular posts.

== Screenshots ==

1. Admin panel - Featured listings tab with drag-and-drop ordering
2. Admin panel - Style settings with color pickers and presets
3. Frontend - Grid layout with default card style
4. Frontend - Featured card style with colored header
5. Gutenberg block in the editor

== Changelog ==

= 1.2.1 =
* Conditional Display: New `hide_on_single` attribute to hide spotlight on single listing pages
* Global Setting: "Hide on single listing pages" option in Automation Settings
* Block Support: Gutenberg block now includes hide on single listing dropdown
* CSS Fix: Featured style cards no longer show red background bleeding through images
* Detection works by checking URL pattern against Linear plugin's `dynamic_parent_pages` setting

= 1.2.0 =
* Admin UI Improvements: Tabbed interface with improved navigation
* Sidebar Navigation: Submenu links in WordPress admin sidebar for direct tab access
* Enhanced Documentation: Improved help texts on all tabs
* Finnish Translation: Plugin name translated to "Näyteikkuna" in Finnish
* Date Format: Finnish date format (d.m.Y) for listing dates
* Border Radius System: Standardized border-radius values using CSS custom properties
* UI Polish: Modern card-style sections, improved form styling
* 100+ additional Finnish translations

= 1.1.0 =
* Analytics Tracking: GA4, GTM, and Matomo event tracking for impressions and clicks
* Listing Expiration: Auto-remove listings after configurable number of days
* Sold Listing Filtering: Automatically hide sold/rented listings
* Scheduled Featuring: Set start and end dates for each featured listing
* Import/Export: Backup and transfer settings between sites (JSON format)
* Status Indicators: Shows Active, Scheduled, or Expired status per listing
* WP Cron: Daily cleanup job removes expired listings automatically
* 50+ new Finnish translations

= 1.0.3 =
* Updated minimum requirements: WordPress 6.4+, PHP 8.3+
* PHP 8.2+ compatibility: typed properties, union types
* Fixed SQL injection vulnerability in cache clearing
* Added uninstall.php for proper cleanup on plugin deletion
* Updated Gutenberg block to API version 3
* Added REST API input validation schemas

= 1.0.2 =
* Renamed plugin to "Property Spotlight"
* All CSS classes, options, and shortcodes updated to use `property-spotlight` prefix

= 1.0.1 =
* Fixed image extraction from API (supports both v2 and v3 response formats)
* Fixed permalink generation (reads official Linear plugin's settings)
* Fixed Finnish translations (compiled .mo file now included)
* Fixed ZIP build script (correct folder structure for WordPress)
* Added Clear Cache button in admin panel
* TranslatePress support for multi-language permalink URLs

= 1.0.0 =
* Initial release
* Linear.fi API v2/v3 support
* Standalone and official Linear plugin credential modes
* Grid, list, carousel layouts
* Default, featured, compact, dark card styles
* Admin style settings with presets
* Gutenberg block
* Finnish translations

== Upgrade Notice ==

= 1.2.1 =
New hide_on_single feature for single listing pages. CSS fix for featured style.

= 1.2.0 =
Improved admin UI with tabbed interface and sidebar navigation. 100+ new Finnish translations.

= 1.1.0 =
Analytics tracking, scheduled featuring, and import/export added.

= 1.0.0 =
Initial release.
