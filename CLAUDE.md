# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What This Is

A WordPress plugin (PHP 8.2+, WP 6.4+) that lets users manually curate featured real estate listings sourced from the **Linear.fi API** and display them via shortcode or Gutenberg block.

## Commands

**Local dev environment:**
```bash
docker compose up -d        # Start WordPress at http://localhost:8089
docker compose down         # Stop
docker compose logs -f      # Watch logs
```
Plugin is mounted live into the container — no rebuild needed for PHP/CSS/JS changes.

**Build release ZIP:**
```bash
./build-zip.sh              # Creates property-spotlight.zip
```

**PHP lint (matches CI):**
```bash
parallel-lint --exclude vendor .
```
CI runs lint against PHP 8.2, 8.3, and 8.4 on every push to `main`.

**Release:** Push a `v*` tag → GitHub Actions builds the ZIP and creates a GitHub Release automatically.

**Translations:** Edit `.po` files in `languages/`, then run `languages/compile-mo.php` to compile `.mo` files.

## Architecture

### Entry point: `property-spotlight.php`

Defines constants, registers activation/deactivation hooks, schedules a daily WP-Cron job (`property_spotlight_daily_cleanup`) for expiring featured listings, then loads all classes via `plugins_loaded` at priority 20.

### Class structure (all in `includes/`)

| Class | File | Role |
|---|---|---|
| `Property_Spotlight` | `class-property-spotlight.php` | Singleton orchestrator. Instantiates all other classes, registers REST routes (`/property-spotlight/v1/`), enqueues frontend assets. |
| `Property_Spotlight_API` | `class-property-spotlight-api.php` | Fetches from Linear.fi API, normalizes listing data, manages transient cache. |
| `Property_Spotlight_Admin` | `class-property-spotlight-admin.php` | Admin menu, all AJAX handlers, access control logic. |
| `Property_Spotlight_Shortcode` | `class-property-spotlight-shortcode.php` | Registers `[property_spotlight]` shortcode, renders HTML output. |
| `Property_Spotlight_Block` | `class-property-spotlight-block.php` | Registers the Gutenberg block; delegates rendering to shortcode via `do_shortcode`. |

### Data flow

1. `Property_Spotlight_API::get_all_listings()` fetches from Linear API and stores results in a WP transient (default 30min TTL, keyed by URL+API key+language).
2. `get_featured_listings()` filters that cached result down to the admin-selected IDs, applying schedule/expiry/sold-status rules.
3. Shortcode and block both call `get_featured_listings()` at render time.

### API credential fallback

The plugin first looks for its own credentials (`property_spotlight_settings`). If absent, it falls back to the **official Linear WordPress plugin** settings (`linear_settings`). This means the plugin works out-of-the-box if the official plugin is already installed.

### Linear API versions

The API class auto-detects version from the URL:
- **v2** (`externalapi` / `azurecontainerapps` in URL): Header-based auth (`Authorization: LINEAR-API-KEY …`), returns a flat JSON array.
- **v3** (`data.linear.fi`): Key in URL path, returns `{ data: [...] }`.

### Featured ID metadata format

`featured_ids` in `property_spotlight_settings` stores an array of objects (not plain strings):
```json
[{ "id": "ABC123", "added": 1700000000, "expires": null, "start": null, "end": null }]
```
`property_spotlight_migrate_featured_ids()` in the main plugin file handles upgrading old plain-string arrays.

### Access control

Admins (`manage_options`) always have full access. Non-admins can be granted access to the Listings tab via allowed roles/users in Settings → Access Control. Settings, Style, and API config are always admin-only.

### CSS

Frontend uses **BEM** naming (`.property-spotlight`, `.property-spotlight__item`, `--grid`/`--list`/`--carousel`/`--featured`/`--compact`/`--dark` modifiers). Style values (colors, radius) from admin settings are injected as inline CSS variables on the wrapper element, not as a separate stylesheet.

### Gutenberg block

`blocks/spotlight/index.js` is vanilla JS (no build step, no `node_modules`). It uses the `wp.*` globals provided by WordPress. The block is a **dynamic block** — `save()` returns `null` and PHP renders the output via `render_block()` which calls `do_shortcode()`.

### i18n

Text domain: `property-spotlight`. All strings go through `__()` / `esc_html_e()`. Finnish translation: `languages/property-spotlight-fi.po`.
