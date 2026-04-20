=== Property Spotlight ===
Contributors: markusmedia
Tags: real estate, property, listings, linear, spotlight
Requires at least: 6.4
Tested up to: 7.0
Stable tag: 1.1.0
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

Official details and Swagger for the test host: [Linear API – Listing Data](https://support.linear.fi/hc/en-fi/articles/31404123207069-API-Integration-Listing-Data). If the test URL stops responding (timeouts or connection errors), confirm the URL and key in that article still match your settings; the sandbox is hosted by Linear and may be unavailable until they restore it or publish a new endpoint.

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

= 1.0.0 =
* Initial release
* Linear.fi API v2/v3 support with automatic version detection
* Standalone and official Linear plugin credential modes
* Grid, list, and carousel layouts
* Default, featured, compact, and dark card styles
* Admin panel with tabbed interface (Listings, Settings, Style, Documentation)
* Gutenberg block with live editor preview
* Shortcode `[property_spotlight]` with full attribute support
* Style customization with color pickers and presets
* Analytics tracking: GA4, GTM, Matomo (opt-in)
* Scheduled featuring with per-listing start/end dates
* Auto-expiration after configurable number of days
* Automatic sold/rented listing filtering
* Import/Export settings as JSON
* Access control for non-admin users
* Hide spotlight on single listing pages option
* Finnish translations (330+ strings)
* GitHub Actions CI/CD with automated release builds

== Upgrade Notice ==

= 1.0.0 =
Initial release.
