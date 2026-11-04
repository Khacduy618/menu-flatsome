# Handdn Menu Manager Admin Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Upgrade the Device Menu Manager WordPress plugin admin interface and data schema to a unified responsive menu builder with rich metadata (Primary/Secondary sections, Group Headings, Sizes Grid, Editorial Visual Banners, Feature Cards, 3D Custom Promo, and Trustpilot Footer integration) matching `Handdn Menu Prototype.html`.

**Architecture:** A single unified responsive menu tree replaces separate mobile/desktop trees, stored in `dmm_menu_data` with backward-compatible migration from `dmm_desktop_menu_data`. The admin UI introduces a 3-tab navigation (Menu Builder, Drawer Promo & Footer, General Settings) with a tabbed Inspector Modal (General, Visual Banner, Feature Card) integrated with `wp.media` and `wp-color-picker`.

**Tech Stack:** PHP 7.4+, WordPress Core APIs (`wp_enqueue_media`, `wp-color-picker`, `wp_send_json_success`, Options API), jQuery, jQuery UI Sortable, CSS3.

**Spec:** [docs/superpowers/specs/2026-11-03-handdn-admin-menu-design.md](file:///Users/khacduy/Documents/project-wp/device-menu-manager/docs/superpowers/specs/2026-11-03-handdn-admin-menu-design.md)

## Global Constraints

- PHP 7.4+ compatibility; no modern PHP 8.1+ syntax (no typed properties or readonly classes).
- WordPress Core compatibility: 5.8 through 6.7+.
- All menu item input sanitized via `DMM_Menu_Data_Manager::sanitize_menu_items()`.
- Visual metadata must remain attached directly to each menu item object to prevent ID dissociation.
- Single responsive master tree: `dmm_menu_data` is the authoritative option key.

## Review Focus

1. **Heading items submitted with dummy URLs**: When `item_type: "heading"` is selected, URL should be forced to `#` or empty and excluded from frontend links.
2. **Visual banner enabled without image or texture**: Fallback cleanly to default texture (`grain`) and default tone (`#3b2416`).
3. **Child items inheriting section**: Root items define `section` (`primary` | `secondary`); child items must inherit their root ancestor's section automatically.
4. **Trustpilot auto-detect failure**: If no Trustpilot plugin is active, fall back cleanly to manual fields (`4.7` rating, `619` reviews) without throwing PHP warnings.
5. **Raw JSON Editor schema roundtrip**: Copying and pasting raw JSON must preserve `visual`, `feature`, `item_type`, and `tag` properties without stripping.

---

### Task 1: Data Manager & Extended Schema Sanitizer

**Files:**
- Modify: `includes/class-menu-data-manager.php:15-180`
- Test: `tests/test-data-manager.php`

**Interfaces:**
- Consumes: WordPress Options API (`get_option`, `update_option`).
- Produces: `DMM_Menu_Data_Manager::get_menu_items()`, `DMM_Menu_Data_Manager::save_menu_items(array $items)`, `DMM_Menu_Data_Manager::sanitize_menu_items(array $items)`, `DMM_Menu_Data_Manager::get_trustpilot_data()`.

- [ ] **Step 1: Write the test script for extended schema sanitization**

Create `tests/test-data-manager.php` verifying that:
1. `section`, `item_type`, `tag`, `visual`, and `feature` are preserved and sanitized.
2. An item with `item_type: "heading"` sanitizes URL to `#`.
3. Legacy items without new fields receive safe defaults.
4. `get_menu_items()` migrates from `dmm_desktop_menu_data` if `dmm_menu_data` is empty.

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/test-data-manager.php`
Expected: FAIL with missing fields or undefined methods.

- [ ] **Step 3: Implement extended schema in `includes/class-menu-data-manager.php`**

1. Update constant `OPTION_MENU_DATA = 'dmm_menu_data'`.
2. Update `get_menu_items()` to read `dmm_menu_data`, falling back to `dmm_desktop_menu_data` if present, then to `get_sample_data()`.
3. Update `save_menu_items(array $items)` to save to `dmm_menu_data`.
4. Update `sanitize_menu_items(array $items)` to sanitize:
   - `section`: sanitize_key (`primary` or `secondary`, default `primary`).
   - `item_type`: sanitize_key (`link`, `heading`, or `sizes_grid`, default `link`).
   - `tag`: sanitize_text_field.
   - `visual`: array with `enabled` (bool), `media_type` (`texture`|`image`), `image_url` (esc_url_raw), `tex` (sanitize_key), `tone` (sanitize_hex_color), `kicker`, `headline`, `cta_text`, `cta_url`.
   - `feature`: array with `enabled` (bool), `media_type`, `image_url`, `tex`, `tone`, `kicker`, `text`, `cta_text`, `cta_url`.
5. Implement `get_trustpilot_data()` method checking for Trustpilot plugin options and falling back to plugin settings defaults (`4.7` / `619`).
6. Update `get_sample_data()` with the full HandDN dataset containing visual textures and group headings matching `Handdn Menu Prototype.html`.

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/test-data-manager.php`
Expected: PASS (All assertions green).

- [ ] **Step 5: Verify syntax**

Run: `php -l includes/class-menu-data-manager.php`
Expected: No syntax errors detected.

---

### Task 2: Admin Settings Enqueues & Template Structure

**Files:**
- Modify: `includes/class-admin-settings.php:15-120`
- Modify: `templates/admin-menu-builder.php:1-297`

**Interfaces:**
- Consumes: `wp_enqueue_media()`, `wp_enqueue_style( 'wp-color-picker' )`, `wp_enqueue_script( 'wp-color-picker' )`.
- Produces: Modern 3-tab layout (`#dmm-tab-builder`, `#dmm-tab-drawer`, `#dmm-tab-settings`), Inspector Modal container `#dmm-inspector-modal`.

- [ ] **Step 1: Update `class-admin-settings.php` for media and color picker**

1. In `DMM_Admin_Settings::enqueue_assets()`, add:
   - `wp_enqueue_media();`
   - `wp_enqueue_style( 'wp-color-picker' );`
   - `wp_enqueue_script( 'wp-color-picker' );`
2. Update `wp_localize_script()` data:
   - Pass single `menuData` (`DMM_Menu_Data_Manager::get_menu_items()`).
   - Pass `drawerSettings` (`get_option('dmm_settings')`).
   - Pass `trustpilotData` (`DMM_Menu_Data_Manager::get_trustpilot_data()`).

- [ ] **Step 2: Update `templates/admin-menu-builder.php` layout**

1. Replace the Desktop/Mobile/Settings tabs with:
   - Tab 1: **Navigation Menu Builder** (`#builder`)
   - Tab 2: **Drawer Promo & Footer** (`#drawer`)
   - Tab 3: **General Settings** (`#settings`)
2. In Tab 1 (`#builder`):
   - Toolbar with: `+ Add Link`, `+ Add Group Heading`, `+ Add Sizes Grid`, `Reset Sample Data`, `Collapse All`, `Expand All`, `Raw JSON Editor`.
   - Tree list `#dmm-tree-main` (single sortable list).
   - Raw JSON editor drawer.
3. In Tab 2 (`#drawer`):
   - Card 1: **Root Level 0 Promo Banner** (3D Custom Strap card: Image/Texture selector, Tone color, Kicker, Headline, CTA text/link, SVG outline toggle).
   - Card 2: **Trustpilot Rating & Reviews** (Auto-detect toggle, Manual Rating, Manual Review Count, Trustpilot URL).
   - Card 3: **WooCommerce & Client Service Links** (Toggles for My Account, Track Order, Contact Us, and Custom links).
4. Add the Inspector Modal template `#dmm-inspector-modal`:
   - Tab navigation: `[Link & Content]`, `[Editorial Visual Banner]`, `[Sub-panel Feature Card]`.
   - Tab 1 fields: Title, Item Type dropdown (`Standard Link`, `Group Heading`, `Sizes Grid`), URL, Section toggle (Primary/Secondary, visible on root items), Tag input (`Must try`, etc.), CSS classes.
   - Tab 2 fields: Visual toggle, Media Type (`Canvas Texture Preset` | `Uploaded Image`), Image upload button & preview, Texture preset dropdown (`grain`, `scales`, `dots`, `quill`, `weave`, `cross`, `smooth`), Tone color picker, Kicker input, Headline textarea, CTA Text input, CTA URL input.
   - Tab 3 fields: Feature card toggle, Media Type, Image upload, Texture preset, Tone color, Kicker, Description text, CTA Text, CTA URL.
   - Action buttons: `Apply to Item`, `Cancel`.

- [ ] **Step 3: Verify syntax**

Run: `php -l includes/class-admin-settings.php && php -l templates/admin-menu-builder.php`
Expected: No syntax errors detected.

---

### Task 3: Admin Tree Builder Engine & Tabbed Inspector Logic

**Files:**
- Modify: `assets/js/admin-builder.js:1-550`

**Interfaces:**
- Consumes: `window.dmmAdmin.menuData`, `window.dmmAdmin.settings`, `wp.media`, `jQuery.fn.wpColorPicker`.
- Produces: Dynamic tree rendering with badges, drag-and-drop hierarchy, Inspector Modal management, JSON bidirectional sync, global save handler.

- [ ] **Step 1: Refactor `admin-builder.js` state store for single tree**

1. Replace `data: { desktop: [], mobile: [] }` with `data: window.dmmAdmin.menuData || []`.
2. Update `init()` to:
   - Initialize the single tree on `#dmm-tree-main`.
   - Initialize color pickers on tone fields.
   - Initialize WordPress Media Uploader triggers.
   - Initialize Inspector Modal tabs and events.

- [ ] **Step 2: Implement item node rendering with distinct badges**

1. Update `createItemNode(item, depth)`:
   - If `depth === 0`: render clickable section badge `[PRIMARY]` (blue) or `[SECONDARY]` (gold). Clicking toggles `item.section` and re-renders tree.
   - If `item.item_type === 'heading'`: render badge `[HEADING]` with distinct styling; display title in bold uppercase; display `(Group Section Divider)` instead of URL.
   - If `item.item_type === 'sizes_grid'`: render badge `[SIZES GRID 14-26mm]`.
   - If `item.visual && item.visual.enabled`: render badge `[VISUAL]`.
   - If `item.tag`: render badge `[TAG: {tag}]`.
2. Keep drag handle and indentation buttons (`←` and `→`).

- [ ] **Step 3: Implement Tabbed Inspector Modal**

1. `openInspector(itemId)`:
   - Populate Tab 1 with title, item_type, url, tag, classes, section.
   - Populate Tab 2 with visual fields; initialize or update `wpColorPicker` on `#dmm-inspector-visual-tone`.
   - Populate Tab 3 with feature fields; initialize `wpColorPicker` on `#dmm-inspector-feature-tone`.
   - Switch to Tab 1 by default; display modal with animation.
2. Implement Media Uploader bridge (`wp.media`):
   - Clicking "Select/Upload Image" opens the WordPress Media Library modal.
   - Selecting an image populates the image URL input and updates a thumbnail preview.
3. `saveInspector(itemId)`:
   - Extract values from all 3 tabs into the target item object in `DMM.data`.
   - Re-render tree node; update Raw JSON editor.
   - Close modal.

- [ ] **Step 4: Implement toolbar actions and global save**

1. Add "Add Link" button: appends new standard link item.
2. Add "Add Group Heading" button: appends new item with `item_type: "heading"`, `title: "NEW SECTION"`, `url: "#"`.
3. Add "Add Sizes Grid" button: appends new item with `item_type: "sizes_grid"`, `title: "Shop by lug width"`, `url: "#"`.
4. Update `saveAll()`:
   - Bundle `DMM.data` as `menu_data`.
   - Bundle Drawer Promo & Footer settings as `drawer_settings`.
   - Send via AJAX `dmm_save_menu_data` and display toast notification.

---

### Task 4: Admin Builder Styling & Visual Design

**Files:**
- Modify: `assets/css/admin-builder.css:1-600`

**Interfaces:**
- Consumes: WordPress admin theme styles.
- Produces: Premium styling for Tree items, badges, Inspector Modal, and Drawer Settings tab.

- [ ] **Step 1: Style Tree item types and badges**

1. Section badges:
   - `.dmm-section-primary`: Navy background (`#14162B`), white text, cursor pointer.
   - `.dmm-section-secondary`: Amber/Gold background (`#9E6B3A`), white text, cursor pointer.
2. Item type styling:
   - `.dmm-item-heading`: Distinct border-left (4px solid `#9E6B3A`), subtle background wash, bold uppercase title typography.
   - `.dmm-badge-heading`: Badge with tag `HEADING`.
   - `.dmm-badge-sizes`: Badge with tag `SIZES GRID`.
   - `.dmm-badge-visual`: Glowing emerald badge `VISUAL` indicating active visual banner.
   - `.dmm-badge-tag`: Rose badge for custom tags (`Must try`, etc.).

- [ ] **Step 2: Style the Inspector Modal**

1. `.dmm-modal-overlay`: Centered backdrop with blur and soft fade-in.
2. `.dmm-modal-dialog`: 720px wide card with header, tab navigation bar, scrollable body (max-height 75vh), and sticky footer actions.
3. Tab navigation `.dmm-modal-nav`: Clean underlined tabs with active indicators.
4. Color picker container and preview swatches: Ensure `wp-color-picker` renders cleanly without layout clipping.
5. Image preview box: Responsive image thumbnail with "Remove Image" button.

- [ ] **Step 3: Style the Drawer Promo & Footer Settings Cards**

1. Grid layout for Promo banner fields (2-column layout on desktop, stacked on mobile).
2. Clean toggle switches for Trustpilot auto-detect and WooCommerce utility links.

---

### Task 5: End-to-End Verification & Default Prototype Dataset

**Files:**
- Modify: `includes/class-menu-data-manager.php` (Seed data update)
- Test: `tests/test-end-to-end.php`

**Interfaces:**
- Consumes: All components from Tasks 1–4.
- Produces: Fully functional admin menu builder populated with complete Handdn prototype dataset.

- [ ] **Step 1: Populate `get_sample_data()` with complete Handdn Prototype data**

Include in default sample data:
- Primary:
  - `Watch Straps` (Visual: grain / #6b3b20, Feature: Leather collection)
    - `All watch straps`, `In stock, ready to ship`, `Leather collection`
    - `BY MATERIAL` (Heading)
      - `Exotic leathers` (Visual: scales / #3b2416) -> sub-links (Alligator [Visual], Shell Cordovan [Visual], Stingray...)
      - `Regular leathers` (Visual: grain / #1f2a3d) -> sub-links (Calfskin, Tanneries)
      - `Non-leathers` (Visual: weave / #4b4f36) -> sub-links (Alcantara, Canvas...)
    - `BY FIT` (Heading)
      - `Strap styles` (Visual: smooth / #9a6234) -> sub-links (Classic, Curved End, Bund...)
      - `By watch brand` (Visual: grain / #1d1b1a) -> Apple Watch, Panerai...
      - `Shop by lug width` (item_type: `sizes_grid`, Visual: smooth / #b07a3a)
  - `Bespoke` (Visual: scales / #5a2f1b, Feature: Craft) -> 3D custom, Custom strap...
  - `Apple Watch Bands` (Visual: grain / #2a2624)
  - `In Stock`
  - `Gifts` (Visual: smooth / #5c1f24)
- Secondary:
  - `Discover Handdn` (Visual: grain / #7a5a3a)
  - `Client Services` (Visual: smooth / #22304a)

- [ ] **Step 2: Write and run end-to-end verification script**

Create `tests/test-end-to-end.php` testing:
1. Complete sample dataset deserializes and passes `sanitize_menu_items()`.
2. Hierarchical tree builder correctly identifies root primary vs secondary items.
3. Headings contain their children properly for tree hierarchy.
4. Drawer promo and Trustpilot settings save and validate.

Run: `php tests/test-end-to-end.php`
Expected: PASS with 100% assertions green.
