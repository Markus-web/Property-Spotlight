# Property Spotlight Plugin — Full Code Sweep Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fix all identified bugs, performance issues, and code quality problems across four severity-ordered commits.

**Architecture:** Changes are confined to existing files — no new files needed. Each commit is independent and shippable. PHP lint (`parallel-lint --exclude vendor .`) must pass after each commit.

**Tech Stack:** PHP 8.2+, WordPress 6.4+, no build step required.

---

## Files Modified

| File | Commit(s) | What changes |
|------|-----------|-------------|
| `includes/class-property-spotlight.php` | 1, 2 | REST route schema + `rest_save_settings()` rewrite; return types on `get_option()`, `get_featured_ids()` |
| `includes/class-property-spotlight-api.php` | 2, 3, 4 | O(n×m) loop → map; return types; error logging; TranslatePress null guard; `get_permalink()` false check |
| `includes/class-property-spotlight-shortcode.php` | 2, 3 | `REQUEST_URI` sanitization; carousel dedup; `strtok()` replacement |
| `includes/class-property-spotlight-admin.php` | 3, 4 | JSON error logging; user list cache; `wp_roles()` guard; import size limit |
| `property-spotlight.php` | 3, 4 | Admin notice capability check; `plugins_loaded` priority comment |

---

## Task 1 — Fix REST API `featured_ids` metadata loss (Commit 1)

**Files:**
- Modify: `includes/class-property-spotlight.php:130-148` (route schema)
- Modify: `includes/class-property-spotlight.php:193-210` (`rest_save_settings`)

**Background:** `rest_save_settings()` runs `array_map('sanitize_text_field', $featured_ids)`, which destroys the `{id, added, expires, start, end}` metadata structure. The REST schema also only accepts `array of strings`, so clients can't even send metadata. This causes silent data loss — scheduling and expiry stop working after any REST save.

- [ ] **Step 1: Update the REST route schema for `featured_ids`**

Replace lines 140–148 in `includes/class-property-spotlight.php`:

```php
'featured_ids' => [
    'type'     => 'array',
    'required' => true,
    'items'    => [
        'type'       => 'object',
        'properties' => [
            'id'      => ['type' => 'string'],
            'added'   => ['type' => ['integer', 'null']],
            'expires' => ['type' => ['integer', 'null']],
            'start'   => ['type' => ['integer', 'null']],
            'end'     => ['type' => ['integer', 'null']],
        ],
        'required' => ['id'],
    ],
],
```

Remove the `'sanitize_callback'` line entirely — sanitization moves to `rest_save_settings()`.

- [ ] **Step 2: Rewrite `rest_save_settings()` to preserve metadata**

Replace the entire method body (lines 193–210):

```php
public function rest_save_settings(\WP_REST_Request $request): \WP_REST_Response {
    $featured_ids = $request->get_param('featured_ids');

    if (!is_array($featured_ids)) {
        return new \WP_REST_Response(['error' => 'Invalid featured_ids'], 400);
    }

    $sanitized = [];
    foreach ($featured_ids as $item) {
        if (!is_array($item) || empty($item['id'])) {
            continue;
        }
        $sanitized[] = [
            'id'      => sanitize_text_field($item['id']),
            'added'   => isset($item['added']) && null !== $item['added'] ? absint($item['added']) : null,
            'expires' => isset($item['expires']) && null !== $item['expires'] ? absint($item['expires']) : null,
            'start'   => isset($item['start']) && null !== $item['start'] ? absint($item['start']) : null,
            'end'     => isset($item['end']) && null !== $item['end'] ? absint($item['end']) : null,
        ];
    }

    $this->options['featured_ids'] = $sanitized;
    update_option('property_spotlight_settings', $this->options);

    delete_transient('property_spotlight_featured_cache');

    return new \WP_REST_Response(['success' => true, 'featured_ids' => $sanitized], 200);
}
```

- [ ] **Step 3: Run PHP lint**

```bash
parallel-lint --exclude vendor .
```

Expected: `No errors found`

- [ ] **Step 4: Commit**

```bash
git add includes/class-property-spotlight.php
git commit -m "fix: preserve featured_ids metadata structure in REST API save

rest_save_settings() was flattening {id,added,expires,start,end} objects
to bare strings via array_map('sanitize_text_field'), silently erasing
scheduling and expiry data. Also update REST route schema to accept objects
so clients can actually send the metadata."
```

---

## Task 2 — Performance & type safety (Commit 2)

**Files:**
- Modify: `includes/class-property-spotlight-api.php:178,196-211,84,178,291`
- Modify: `includes/class-property-spotlight.php:252,259`
- Modify: `includes/class-property-spotlight-shortcode.php:414,419`

### 2a — O(n×m) → O(n+m) in `get_featured_listings()`

- [ ] **Step 1: Replace nested loop with index map**

In `includes/class-property-spotlight-api.php`, replace lines 197–211:

```php
// Build ID→listing index for O(1) lookup
$listings_by_id = array_column($all_listings, null, 'id');

foreach ($active_items as $item) {
    $id = is_array($item) ? ($item['id'] ?? '') : (string) $item;
    if ('' === $id || !isset($listings_by_id[$id])) {
        continue;
    }
    $listing = $listings_by_id[$id];
    if ($auto_remove_sold && $this->is_listing_sold($listing)) {
        continue;
    }
    $featured[] = $listing;
}
```

### 2b — Fix `REQUEST_URI` sanitization

- [ ] **Step 2: Replace `sanitize_text_field` with `esc_url_raw` in `is_single_listing_view()`**

In `includes/class-property-spotlight-shortcode.php`, replace line 414:

```php
$current_url = isset($_SERVER['REQUEST_URI']) ? esc_url_raw(wp_unslash($_SERVER['REQUEST_URI'])) : '';
```

### 2c — Fix `strtok()` global state side-effect

- [ ] **Step 3: Replace `strtok()` with `explode()`**

In `includes/class-property-spotlight-shortcode.php`, replace line 419:

```php
$current_url = trailingslashit(explode('?', $current_url, 2)[0]);
```

### 2d — Add missing return type declarations

- [ ] **Step 4: Add return types to API class**

In `includes/class-property-spotlight-api.php`:

Line 84 — change:
```php
public function get_all_listings(string $lang = 'fi') {
```
to:
```php
public function get_all_listings(string $lang = 'fi'): array|\WP_Error {
```

Line 178 — change:
```php
public function get_featured_listings(string $lang = 'fi', int $limit = 0) {
```
to:
```php
public function get_featured_listings(string $lang = 'fi', int $limit = 0): array|\WP_Error {
```

`get_listing()` already has `: ?array` — no change needed.

- [ ] **Step 5: Add return types to core class**

In `includes/class-property-spotlight.php`:

Line 252 — change:
```php
public function get_option(string $key, $default = null) {
```
to:
```php
public function get_option(string $key, mixed $default = null): mixed {
```

Line 259 — change:
```php
public function get_featured_ids(): array {
```
Already has return type — no change needed.

- [ ] **Step 6: Run PHP lint**

```bash
parallel-lint --exclude vendor .
```

Expected: `No errors found`

- [ ] **Step 7: Commit**

```bash
git add includes/class-property-spotlight-api.php includes/class-property-spotlight-shortcode.php includes/class-property-spotlight.php
git commit -m "perf+types: O(n+m) featured lookup, fix REQUEST_URI sanitization, add return types"
```

---

## Task 3 — Medium: Robustness & UX (Commit 3)

**Files:**
- Modify: `includes/class-property-spotlight-shortcode.php` (carousel dedup)
- Modify: `property-spotlight.php` (admin notice capability)
- Modify: `includes/class-property-spotlight-api.php` (error log, TranslatePress guard)
- Modify: `includes/class-property-spotlight-admin.php` (user cache, JSON error log)

### 3a — Carousel script deduplication

- [ ] **Step 1: Add static guard to `get_carousel_script()`**

In `includes/class-property-spotlight-shortcode.php`, replace line 338–339:

```php
private function get_carousel_script(): string {
    static $printed = false;
    if ($printed) {
        return '';
    }
    $printed = true;

    return '
    <script>
    // ... (rest of existing script unchanged)
```

Keep the rest of the method body exactly as-is.

### 3b — Admin notice capability check

- [ ] **Step 2: Add `manage_options` guard to `property_spotlight_admin_notice()`**

In `property-spotlight.php`, add capability check as the first line inside the function (after line 54 `function property_spotlight_admin_notice() {`):

```php
function property_spotlight_admin_notice() {
    if (!current_user_can('manage_options')) {
        return;
    }

    // Only show on non-plugin pages
    $screen = get_current_screen();
    // ... rest unchanged
```

### 3c — API failure error logging

- [ ] **Step 3: Add `error_log()` in `get_all_listings()` on WP_Error**

In `includes/class-property-spotlight-api.php`, replace lines 119–133:

```php
if (is_wp_error($response)) {
    $message = $response->get_error_message();
    error_log('[Property Spotlight] API request failed: ' . $message);
    if (preg_match('/timed out|timeout/i', $message)) {
        return new \WP_Error(
            'api_timeout',
            trim(
                $message . ' ' . __(
                    'Verify the Data URL is reachable over HTTPS from this server. Linear\'s public test API may occasionally be unavailable; see the Linear API article for the current test URL, or contact Linear support.',
                    'property-spotlight'
                )
            )
        );
    }
    return $response;
}
```

### 3d — TranslatePress null guard

- [ ] **Step 4: Guard `get_component()` return value in `get_parent_page_url()`**

In `includes/class-property-spotlight-api.php`, replace lines 373–378:

```php
if (class_exists('TRP_Translate_Press')) {
    $trp = \TRP_Translate_Press::get_trp_instance();
    $url_converter = $trp->get_component('url_converter');
    if (null !== $url_converter && method_exists($url_converter, 'get_url_for_language')) {
        $url = esc_url($url_converter->get_url_for_language($lang, $url, ''));
    }
}
```

### 3e — User list caching in admin

- [ ] **Step 5: Cache `get_users()` result for 60 seconds**

In `includes/class-property-spotlight-admin.php`, replace line 615:

```php
$cache_key = 'property_spotlight_all_users';
$all_users = wp_cache_get($cache_key);
if (false === $all_users) {
    $all_users = get_users(['fields' => ['ID', 'display_name', 'user_email']]);
    wp_cache_set($cache_key, $all_users, '', 60);
}
```

### 3f — JSON decode error logging in `ajax_save_featured()`

- [ ] **Step 6: Log JSON decode failures**

In `includes/class-property-spotlight-admin.php`, replace lines 1141–1143:

```php
if (json_last_error() !== JSON_ERROR_NONE) {
    error_log('[Property Spotlight] Failed to decode featured_ids JSON: ' . json_last_error_msg());
    $featured_ids = [];
}
```

- [ ] **Step 7: Run PHP lint**

```bash
parallel-lint --exclude vendor .
```

Expected: `No errors found`

- [ ] **Step 8: Commit**

```bash
git add includes/class-property-spotlight-shortcode.php property-spotlight.php includes/class-property-spotlight-api.php includes/class-property-spotlight-admin.php
git commit -m "fix: carousel dedup, admin notice cap check, error logging, TranslatePress null guard, user cache"
```

---

## Task 4 — Low: Guards & Documentation (Commit 4)

**Files:**
- Modify: `property-spotlight.php` (priority comment)
- Modify: `includes/class-property-spotlight.php` (REST endpoint comment)
- Modify: `includes/class-property-spotlight-api.php` (`get_permalink()` false check)
- Modify: `includes/class-property-spotlight-admin.php` (`wp_roles()` guard, import size limit)

### 4a — Document `plugins_loaded` priority

- [ ] **Step 1: Add comment explaining priority 20**

In `property-spotlight.php`, replace line 257:

```php
// Priority 20: load after other plugins so the Linear plugin's settings
// are already available for the credential fallback in load_credentials().
add_action('plugins_loaded', function() {
```

### 4b — Document public REST endpoint

- [ ] **Step 2: Add comment to `__return_true` permission callback**

In `includes/class-property-spotlight.php`, replace line 114:

```php
// Public: exposes only listing IDs, no PII. Same data rendered by [property_spotlight] shortcode.
'permission_callback' => '__return_true',
```

### 4c — Explicit `false` check for `get_permalink()`

- [ ] **Step 3: Fix `get_permalink()` check in `get_parent_page_url()`**

In `includes/class-property-spotlight-api.php`, replace line 371:

```php
if (false === $url) {
    error_log('[Property Spotlight] get_permalink() returned false for page ID: ' . $page_id);
    continue;
}
```

The surrounding `if ($url)` block becomes:

```php
$url = get_permalink($page_id);

if (false === $url) {
    error_log('[Property Spotlight] get_permalink() returned false for page ID: ' . $page_id);
    continue;
}
```

### 4d — Guard `wp_roles()->roles`

- [ ] **Step 4: Add `isset()` before accessing `->roles`**

In `includes/class-property-spotlight-admin.php`, replace lines 606–612:

```php
$wp_roles = wp_roles();
$available_roles = [];
if (isset($wp_roles->roles)) {
    foreach ($wp_roles->roles as $role_slug => $role_data) {
        if ($role_slug !== 'administrator') {
            $available_roles[$role_slug] = translate_user_role($role_data['name']);
        }
    }
}
```

### 4e — Import file size limit

- [ ] **Step 5: Add size guard in `ajax_import_settings()`**

In `includes/class-property-spotlight-admin.php`, add size check after line 1333 (after `$json_data` is set):

```php
$json_data = isset($_POST['import_data']) ? sanitize_textarea_field(wp_unslash($_POST['import_data'])) : '';

if (strlen($json_data) > 500000) {
    wp_send_json_error(['message' => __('Import data too large (max 500 KB)', 'property-spotlight')]);
}

if (empty($json_data)) {
```

- [ ] **Step 6: Run PHP lint**

```bash
parallel-lint --exclude vendor .
```

Expected: `No errors found`

- [ ] **Step 7: Commit**

```bash
git add property-spotlight.php includes/class-property-spotlight.php includes/class-property-spotlight-api.php includes/class-property-spotlight-admin.php
git commit -m "chore: guards and inline docs for low-severity findings"
```

---

## Self-Review Checklist

- [x] Commit 1: REST metadata loss — schema updated + sanitization rewrites preserving all 5 fields
- [x] Commit 2: O(n×m) loop replaced; REQUEST_URI uses `esc_url_raw`; `strtok` replaced; return types added
- [x] Commit 3: carousel dedup static flag; admin notice capability check; API error logging; TranslatePress null guard; user list cache; JSON error logging
- [x] Commit 4: priority comment; REST endpoint comment; `get_permalink` false check; `wp_roles` guard; import size limit
- [x] No TBDs or placeholders
- [x] All code blocks are complete and match actual file structure
- [x] `parallel-lint` run after each commit
