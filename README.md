# Property Spotlight

Feature specific property listings on your WordPress website with manual selection controls. Works with Linear.fi API.

## Requirements

- WordPress 6.4+ (tested up to 6.9)
- PHP 8.2+ (compatible with PHP 8.4+)
- Linear.fi API credentials (standalone) or Official Linear plugin installed

## Installation

1. Download `property-spotlight.zip`
2. Go to WordPress Admin > Plugins > Add New > Upload Plugin
3. Upload the ZIP file and activate

## Configuration

### API Settings

Navigate to **Property Spotlight** in the admin menu.

**Option A: Standalone Mode**
- Enter your Linear API Data URL (e.g., `https://your-company.externalapi.linear.fi`)
- Enter your API Key (include the `LINEAR-API-KEY` prefix)

**Option B: Use Official Linear Plugin**
- Install and configure the official Linear plugin
- Property Spotlight will automatically use its credentials and permalink settings

### Test API (for development)

Use these test credentials to try the plugin with sample data:

| Setting | Value |
|---------|-------|
| Data URL | `https://ca-externalapi-test-weu-001.livelyrock-4a193af6.westeurope.azurecontainerapps.io` |
| API Key | `LINEAR-API-KEY b6b23692-e3fa-4525-9709-e021c8a65baa` |

**Note:** These are Linear.fi's public test API credentials, shared for development and testing purposes. They connect to a sandbox environment with sample property data. See [API documentation](https://support.linear.fi/hc/en-fi/articles/31404123207069-API-Integration-Listing-Data) for details.

### Selecting Featured Listings

1. Use the searchable dropdown to find listings by address or ID
2. Or enter a listing ID manually
3. Drag and drop to reorder
4. Click **Save Changes**

### Cache Management

Click **Clear Cache** to refresh listing data from the API. Useful after:
- Changing API credentials
- New listings added in Linear.fi
- Debugging image or URL issues

### Style Settings

Customize the appearance with:
- **Color pickers**: Primary, Accent, Price, Featured Background
- **Border radius**: Card corner roundness (0-24px)
- **Presets**: Kiinteistökolmio, Oikotie, Nettiauto, Minimal, or reset to defaults

## Usage

### Shortcode

```
[property_spotlight]
```

#### Attributes

| Attribute | Description | Values | Default |
|-----------|-------------|--------|---------|
| `limit` | Max listings to display | Any number | All |
| `layout` | Display layout | `grid`, `list`, `carousel` | `grid` |
| `style` | Card design style | `default`, `featured`, `compact`, `dark` | `default` |
| `columns` | Grid columns | `1`, `2`, `3`, `4` | `3` |
| `title` | Section header | Any text | — |
| `class` | Custom CSS class | Any class name | — |
| `show_price` | Show price | `true`, `false` | `true` |
| `show_address` | Show address | `true`, `false` | `true` |
| `show_image` | Show image | `true`, `false` | `true` |
| `show_location` | Show city | `true`, `false` | `true` |
| `show_details` | Show type/rooms/area | `true`, `false` | `true` |
| `lang` | Content language | `fi`, `en`, `sv` | Auto |
| `hide_on_single` | Hide on single listing pages | `auto`, `true`, `false` | `auto` |

### Examples

**Basic grid:**
```
[property_spotlight]
```

**Featured style (NÄYTEIKKUNA):**
```
[property_spotlight style="featured" title="NÄYTEIKKUNA" limit="3"]
```

**Showcase style (NÄYTEIKKUNA):**
```
[property_spotlight style="default" title="NÄYTEIKKUNA" columns="3"]
```

**Compact sidebar:**
```
[property_spotlight style="compact" columns="1" limit="5" show_image="false"]
```

**Full-width carousel:**
```
[property_spotlight layout="carousel" class="full-width"]
```

### Gutenberg Block

Search for **"Property Spotlight"** (or "Näyteikkuna" in Finnish) in the block inserter. The block provides:
- Live preview in editor
- Layout and column controls
- Limit settings
- Hide on single listing option

### PHP Template

```php
<?php echo do_shortcode('[property_spotlight limit="3"]'); ?>
```

## CSS Classes

| Class | Description |
|-------|-------------|
| `.property-spotlight` | Main container |
| `.property-spotlight--grid` | Grid layout |
| `.property-spotlight--list` | List layout |
| `.property-spotlight--carousel` | Carousel layout |
| `.property-spotlight--featured` | Featured style |
| `.property-spotlight--compact` | Compact style |
| `.property-spotlight--dark` | Dark style |
| `.property-spotlight__item` | Individual card |
| `.property-spotlight__image` | Card image |
| `.property-spotlight__content` | Card content |
| `.property-spotlight__address` | Address text |
| `.property-spotlight__price` | Price text |

## CSS Variables

The plugin uses CSS variables for theming:

```css
:root {
    --property-spotlight-primary: #1a1a1a;
    --property-spotlight-accent: #0066cc;
    --property-spotlight-price: #1a1a1a;
    --property-spotlight-featured-bg: #c62828;
    --property-spotlight-radius: 4px;
}
```

## Translations

Finnish translations are included (312 translations). The plugin name displays as "Näyteikkuna" in Finnish.

### Compile .mo File

After editing `.po` files, compile to binary `.mo` format:

```bash
# Using polib (Python - recommended)
pip install polib
cd languages/
python -c "import polib; po = polib.pofile('property-spotlight-fi.po', encoding='utf-8'); po.save_as_mofile('property-spotlight-fi.mo')"

# Using msgfmt (if available)
msgfmt -o property-spotlight-fi.mo property-spotlight-fi.po
```

### Add New Language

1. Copy `languages/property-spotlight.pot` to `property-spotlight-{locale}.po`
2. Translate strings using Poedit or similar
3. Compile to `.mo` file using the commands above

## Development

### Test Environment

```bash
# Start WordPress with Docker
docker compose up -d

# Access: http://localhost:8089
# Login: admin / testpassword123

# Stop and clean up
docker compose down -v
```

Note: The test API credentials above require external network access. Docker containers may have DNS limitations depending on your network configuration.

### Build Production ZIP

```powershell
.\build-zip.ps1
```

The build script automatically verifies:
- Main plugin file exists at `property-spotlight/property-spotlight.php`
- No double-nesting in the ZIP
- All required files are included

### Verify ZIP Structure

Before uploading, you can manually verify the ZIP structure:

```powershell
# PowerShell - List first 10 entries
Add-Type -AssemblyName System.IO.Compression.FileSystem
$zip = [System.IO.Compression.ZipFile]::OpenRead("property-spotlight.zip")
$zip.Entries | Select-Object -First 10 FullName
$zip.Dispose()
```

Expected output:
```
property-spotlight/
property-spotlight/property-spotlight.php
property-spotlight/index.php
property-spotlight/assets/
...
```

If you see double-nesting (e.g., `property-spotlight/property-spotlight/`), the ZIP is corrupted.

## File Structure

```
property-spotlight/
├── assets/
│   ├── css/
│   │   ├── admin.css          # Admin panel styles
│   │   ├── frontend.css       # Frontend card styles
│   │   └── index.php          # Security file
│   ├── js/
│   │   ├── admin.js           # Admin panel functionality
│   │   ├── analytics.js       # GA4/GTM/Matomo tracking
│   │   └── index.php          # Security file
│   └── index.php              # Security file
├── blocks/
│   └── spotlight/
│       ├── block.json         # Gutenberg block definition
│       ├── index.js           # Block JavaScript
│       └── index.php          # Security file
├── includes/
│   ├── class-property-spotlight.php           # Main plugin class
│   ├── class-property-spotlight-api.php       # Linear.fi API handler
│   ├── class-property-spotlight-admin.php     # Admin UI (1800+ lines)
│   ├── class-property-spotlight-shortcode.php # Shortcode renderer
│   ├── class-property-spotlight-block.php     # Gutenberg block handler
│   └── index.php                              # Security file
├── languages/
│   ├── property-spotlight.pot     # Translation template
│   ├── property-spotlight-fi.po   # Finnish translations (312 strings)
│   ├── property-spotlight-fi.mo   # Compiled Finnish translations
│   ├── compile-mo.php             # PHP-based .mo compiler
│   └── index.php                  # Security file
├── index.php              # Security file
├── property-spotlight.php # Main plugin file
├── uninstall.php          # Cleanup on plugin deletion
├── build-zip.ps1          # Build script (not in ZIP)
├── docker-compose.yml     # Test environment (not in ZIP)
└── README.md              # Documentation (not in ZIP)
```

## REST API Endpoints

| Endpoint | Method | Auth | Description |
|----------|--------|------|-------------|
| `/property-spotlight/v1/listings` | GET | edit_posts | All listings |
| `/property-spotlight/v1/featured` | GET | Public | Featured listings |
| `/property-spotlight/v1/settings` | GET/POST | manage_options | Plugin settings |

## Troubleshooting

### "Plugin file does not exist" Error

If you see "Lisäosan tiedostoa ei ole olemassa" (Plugin file does not exist) after installation or the plugin won't uninstall properly, WordPress has cached an incorrect plugin path in the database.

**Fix via WP-CLI (recommended):**
```bash
wp plugin deactivate property-spotlight --allow-root
wp plugin activate property-spotlight --allow-root
```

**Fix via SQL (phpMyAdmin or command line):**
```sql
-- Check what WordPress thinks the plugin path is
SELECT option_value FROM wp_options WHERE option_name = 'active_plugins';

-- Reset active plugins (requires manual reactivation)
UPDATE wp_options SET option_value = 'a:0:{}' WHERE option_name = 'active_plugins';
```

**Fix via FTP/File Manager:**
1. Delete `wp-content/plugins/property-spotlight/` folder completely
2. Run the SQL above to clear the active_plugins option
3. Re-upload and install the ZIP fresh

**Common causes:**
- Plugin was uploaded with incorrect folder name
- Plugin folder was manually renamed on the server
- File permissions prevent WordPress from reading the plugin file
- Previous version installed with different folder structure

### Plugin not showing after activation / Double-Nested Folder

Ensure the folder structure is correct:
```
wp-content/plugins/property-spotlight/property-spotlight.php
```

Not:
```
wp-content/plugins/property-spotlight/property-spotlight/property-spotlight.php  (double-nested)
```

**Fix double-nesting via SSH:**
```bash
cd /path/to/wp-content/plugins

# Move contents up one level
mv property-spotlight/property-spotlight/* property-spotlight/

# Remove the now-empty inner folder
rmdir property-spotlight/property-spotlight

# Verify
ls -la property-spotlight/
```

**Fix via FTP/File Manager:**
1. Navigate to `wp-content/plugins/property-spotlight/`
2. If you see another `property-spotlight` folder inside, move its contents up one level
3. Delete the empty inner folder

### API connection issues

1. Check that Data URL doesn't have a trailing slash
2. Verify API Key includes the `LINEAR-API-KEY` prefix
3. Click **Clear Cache** in the Settings tab
4. Check server can reach external URLs (some hosts block outgoing connections)

## Changelog

### 1.2.1
- **Conditional Display**: New `hide_on_single` attribute to hide spotlight on single listing pages
- **Global Setting**: "Hide on single listing pages" option in Automation Settings
- **Block Support**: Gutenberg block now includes hide on single listing dropdown
- **CSS Fix**: Featured style cards no longer show red background bleeding through images
- Detection works by checking URL pattern against Linear plugin's `dynamic_parent_pages` setting

### 1.2.0
- **Admin UI Improvements**: Tabbed interface with improved navigation (Listings, Settings, Style, Documentation)
- **Sidebar Navigation**: Submenu links in WordPress admin sidebar for direct tab access
- **Enhanced Documentation**: Improved help texts on all tabs with better clarity and examples
- **Finnish Translation**: Plugin name translated to "Näyteikkuna" in Finnish
- **Date Format**: Finnish date format (d.m.Y) for listing dates
- **Border Radius System**: Standardized border-radius values using CSS custom properties
- **UI Polish**: Modern card-style sections, improved form styling, better visual hierarchy
- **Text Improvements**: Concise, scannable descriptions matching documentation tab style
- 100+ additional Finnish translations

### 1.1.0
- **Analytics Tracking**: GA4, GTM, and Matomo event tracking for impressions and clicks
- **Listing Expiration**: Auto-remove listings after configurable number of days
- **Sold Listing Filtering**: Automatically hide sold/rented listings
- **Scheduled Featuring**: Set start and end dates for each featured listing
- **Import/Export**: Backup and transfer settings between sites (JSON format)
- **Status Indicators**: Shows Active, Scheduled, or Expired status per listing
- **WP Cron**: Daily cleanup job removes expired listings automatically
- New admin section: Automation Settings
- New admin section: Import / Export
- Per-listing date pickers in the featured listings list
- 50+ new Finnish translations

### 1.0.3
- Updated minimum requirements: WordPress 6.4+, PHP 8.3+
- PHP 8.2+ compatibility: typed properties, union types, `str_contains()` usage
- Fixed SQL injection vulnerability in cache clearing (using `$wpdb->prepare()`)
- Added `uninstall.php` for proper cleanup on plugin deletion
- Updated Gutenberg block to API version 3
- Added REST API input validation schemas
- Improved options storage (disabled autoload for performance)

### 1.0.2
- Renamed plugin to "Property Spotlight" (removed trademark name)
- All CSS classes, options, and shortcodes updated to use `property-spotlight` prefix
- Shortcode changed from `[linear_spotlight]` to `[property_spotlight]`

### 1.0.1
- Fixed image extraction from API (supports both v2 and v3 response formats)
- Fixed permalink generation (reads official Linear plugin's `dynamic_parent_pages` setting)
- Fixed Finnish translations (compiled `.mo` file now included)
- Fixed ZIP build script (correct folder structure for WordPress)
- Added Clear Cache button in admin panel
- TranslatePress support for multi-language permalink URLs

### 1.0.0
- Initial release
- API v2/v3 support
- Standalone and official plugin credential modes
- Grid, list, carousel layouts
- Default, featured, compact, dark card styles
- Admin style settings with presets
- Gutenberg block
- Finnish translations

## License

GPL-2.0+

## Author

[Markus Media](https://markusmedia.fi)
