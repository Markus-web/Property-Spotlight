# Property Spotlight Plugin — Full Code Sweep Design

**Date:** 2026-04-20  
**Approach:** Severity-ordered commits (critical → high → medium → low)

---

## Commit 1 — Critical: Data Integrity

### Problem
`rest_save_settings()` silently destroys `featured_ids` metadata.

The plugin stores featured IDs as structured objects:
```json
[{ "id": "ABC123", "added": 1700000000, "expires": null, "start": null, "end": null }]
```

`rest_save_settings()` runs `array_map('sanitize_text_field', $featured_ids)`, which flattens this to `["ABC123"]`, erasing scheduling and expiry data. The REST route schema also declares `featured_ids` as `array of strings`, so clients cannot even send metadata.

The daily cleanup cron job then skips these flattened entries (expects metadata format), so expiry/scheduling silently stops working.

### Fix
- Update REST route schema: `featured_ids` → array of objects with fields `id` (string), `added` (integer), `expires` (integer|null), `start` (integer|null), `end` (integer|null)
- Rewrite `rest_save_settings()` sanitization to mirror `ajax_save_featured()`: sanitize `id` with `sanitize_text_field()`, timestamps with `absint()`, nullables with explicit null checks
- Write full metadata structure back to `property_spotlight_settings`

**Files:** `includes/class-property-spotlight.php`

---

## Commit 2 — High: Performance & Type Safety

### O(n×m) nested loop in `get_featured_listings()`
Current code iterates all featured IDs × all listings for each match. Replace with:
```php
$listings_by_id = array_column($all_listings, null, 'id');
foreach ($active_items as $item) {
    $id = is_array($item) ? ($item['id'] ?? '') : $item;
    if (isset($listings_by_id[$id])) {
        $featured[] = $listings_by_id[$id];
    }
}
```
Drops complexity from O(n×m) to O(n+m).

**File:** `includes/class-property-spotlight-api.php`

### `REQUEST_URI` sanitization
`sanitize_text_field()` mangles forward slashes and URL characters. Replace with:
```php
esc_url_raw(wp_unslash($_SERVER['REQUEST_URI']))
```

**File:** `includes/class-property-spotlight-shortcode.php`

### Missing return type declarations
Add explicit return types to:
- `get_all_listings()` → `array|WP_Error`
- `get_featured_listings()` → `array|WP_Error`
- `get_listing()` → `?array`
- `get_option()` → `mixed`
- `get_featured_ids()` → `array`

**Files:** `includes/class-property-spotlight-api.php`, `includes/class-property-spotlight.php`

---

## Commit 3 — Medium: Robustness & UX

### Carousel script deduplication
Multiple `[property_spotlight]` shortcodes on one page emit the carousel JS multiple times. Fix with static flag:
```php
static $script_printed = false;
if ($script_printed) return '';
$script_printed = true;
```

**File:** `includes/class-property-spotlight-shortcode.php`

### Admin notice capability check
`property_spotlight_admin_notice()` shows API config notice to all users including non-admins who cannot act on it. Add `current_user_can('manage_options')` guard.

**File:** `property-spotlight.php`

### API failure error logging
`get_all_listings()` returns `WP_Error` on HTTP failure but never logs it. Add `error_log()` so server logs capture repeated API failures.

**File:** `includes/class-property-spotlight-api.php`

### TranslatePress null guard
`$trp->get_component('url_converter')` return value is used without null check. Add `if (null === $url_converter) return $url;` guard.

**File:** `includes/class-property-spotlight-api.php`

### User list caching in admin
`get_users()` called on every admin page render. Wrap with `wp_cache_get/set` (key: `property_spotlight_all_users`, TTL: 60s).

**File:** `includes/class-property-spotlight-admin.php`

### `strtok()` side-effect
`strtok()` modifies global parser state. Replace with `explode('?', $url, 2)[0]` in `is_single_listing_view()`.

**File:** `includes/class-property-spotlight-shortcode.php`

### JSON decode silent failure
When `json_last_error() !== JSON_ERROR_NONE` in `ajax_save_featured()`, silently falls back to `[]`. Add `error_log()` with error detail.

**File:** `includes/class-property-spotlight-admin.php`

---

## Commit 4 — Low: Documentation & Guards

### `plugins_loaded` priority comment
Priority 20 is intentional (loads after other plugins for credential fallback detection) but undocumented. Add inline comment.

**File:** `property-spotlight.php`

### Public REST endpoint comment
`__return_true` permission callback has no explanation. Add `// Public: exposes only listing IDs, no PII` comment.

**File:** `includes/class-property-spotlight.php`

### `get_permalink()` explicit false check
Change `if ($url)` to `if (false !== $url)` with `error_log()` on false.

**File:** `includes/class-property-spotlight-api.php`

### `wp_roles()->roles` guard
Add `isset()` check before accessing `->roles` property.

**File:** `includes/class-property-spotlight-admin.php`

### Import file size limit
Add `strlen($raw_json) > 500000` guard in `ajax_import_settings()` before `json_decode`. Return `wp_send_json_error('File too large')` if exceeded.

**File:** `includes/class-property-spotlight-admin.php`

---

## Out of Scope

- Rate limiting on AJAX handlers (requires infrastructure decision)
- Audit logging (requires new DB table)
- TranslatePress full API migration (third-party dependency, large scope)
- User list pagination in admin (UX redesign)

---

## Success Criteria

- `rest_save_settings()` preserves full metadata structure round-trip
- No nested O(n×m) loop in `get_featured_listings()`
- All return types declared on public API methods
- No duplicate carousel JS on multi-shortcode pages
- All error paths emit `error_log()` entries
- No PHP warnings on sites with 0 users or missing `wp_roles()->roles`
