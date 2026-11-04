# Design Document: Handdn Menu Manager Admin & Data Architecture

- **Date**: 2026-11-03
- **Topic**: Upgrade Device Menu Manager Admin to fully support Handdn Luxury Cascading Drawer Prototype
- **Status**: Draft / Pending Approval
- **Author**: Antigravity & User

---

## 1. Intent & The Why

Handdn is a bespoke luxury watch strap atelier. The user has created and verified an interactive prototype (`Handdn Menu Prototype.html`) featuring an ultra-luxury navigation drawer:
- **Desktop**: Cascading multi-panel drawer sliding horizontally, paired with a dynamic right-hand Editorial Visual Pane that responds to menu hover (swapping leather swatches, textures, kickers, and CTAs in real-time).
- **Mobile**: Smooth full-screen drill-down drawer with clear parent headers and back navigation.
- **Categorization**: Strict separation between Primary (high-intent catalog) and Secondary (discovery & client services) navigation, grouped sub-lists with uppercase section headers, custom badges (*Must try*, *Hot*, *New*), and specialized panel views like a 4-column lug-width sizes grid.
- **Ancillary Elements**: Promo banner at the foot of Level 0 (e.g., 3D Custom Strap with SVG watch strap outline) and a footer with Trustpilot rating and WooCommerce quick links.

The current WordPress plugin `device-menu-manager` only supports a basic flat list of `{ db_id, title, url, menu_item_parent, classes }` and basic CSS badges. It lacks the structure, metadata, and visual editing capabilities required to power the prototype.

This project upgrades the Admin interface and underlying Data Schema so that store managers can intuitively build, configure, and maintain this luxury navigation experience directly from WordPress.

---

## 2. Goals, Non-Goals, and Anti-Goals

### Goals
1. **Rich Data Schema**: Expand the menu item schema to support `section` (Primary/Secondary inheritance), `item_type` (Standard Link, Group Heading, Sizes Grid), `tag` (custom text tags), `visual` (texture/image, tone color, kicker, headline, CTA), and `feature` (sub-panel bottom card).
2. **Dynamic Hover Visuals for Parent & Child**: Allow both parent categories and child items (e.g., specific leathers) to define visual metadata. When hovered on the frontend, the right-hand editorial pane updates smoothly; if a child has no custom visual, it retains the active parent or previous visual.
3. **Intuitive Admin Tree Builder**:
   - Distinct visual representation for Root Primary vs. Secondary items (with quick switch, children automatically inherit).
   - Distinct visual style for `Group Heading` items (bold uppercase indicator, disabled URL input since it is not a link).
   - Clear badge for `Sizes Grid` items.
4. **Tabbed Item Inspector**: An elegant, organized modal or collapsible inspector per menu item:
   - *Tab 1: Link & General Settings* (Title, URL, Item Type, Tag, Classes).
   - *Tab 2: Editorial Visual Banner* (Canvas texture preset or WordPress Media upload image, Tone color picker, Kicker, Headline, CTA text & URL).
   - *Tab 3: Sub-panel Feature Card* (Image/Texture, Tone, Kicker, Text, CTA).
5. **Drawer Global Settings & Footer**:
   - Root Level 0 Promo banner configuration (3D Custom strap banner).
   - Trustpilot rating integration (auto-pull from WP options/transient with manual overrides).
   - WooCommerce utility links (automatic dynamic resolution for My Account, Order Tracking, Cart Count).
6. **Backward & Forward Compatibility**: Ensure existing menu items remain functional and Raw JSON Editor supports importing/exporting the extended schema seamlessly.

### Non-Goals
1. **Announcement Bar / Ticker**: The rotating announcement bar at the top of the site is out of scope for this plugin (handled by theme or customizer).
2. **Search Sheet Logic**: The search overlay drawer with popular keywords is separate from the core drawer menu builder.
3. **Re-writing Flatsome Theme Core**: The plugin bridges into Flatsome's header builder or replaces the menu trigger without modifying parent theme core files.

### Anti-Goals
- **No Cluttered Form Sprawl**: Avoid placing dozens of input fields directly on the tree row. All advanced visual settings must reside inside cleanly organized inspector tabs.
- **No Orphan Visual Data**: Visual data must belong directly to the menu item object, preventing ID mismatch bugs when items are reordered or moved.

---

## 3. Constraints

- **WordPress Standards**: PHP 7.4+ compatibility, WordPress 5.8 to 6.7+.
- **Vanilla JS / jQuery UI**: The current admin uses WordPress core `jquery-ui-sortable`. New interactions must integrate seamlessly without requiring heavy frontend build steps in admin, or use lightweight clean ES6/jQuery with WordPress native libraries (`wp.media`, `wp-color-picker`).
- **Data Integrity**: Sanitization on save via `DMM_Menu_Data_Manager::sanitize_menu_items()`, escaping on output, and CSRF protection via nonces.

---

## 4. Approaches Considered

### Approach 1: Unified Rich Tree + Collapsible Tabbed Item Inspector (Selected)
- Keeps a unified drag-and-drop tree in Admin.
- Level 0 items have a `[Primary] | [Secondary]` toggle.
- Menu items support 3 types: `Link`, `Group Heading`, `Sizes Grid`.
- Clicking "Edit" opens a clean 3-tab inspector (General, Visual Banner, Feature Card).
- Visual Banner is configurable on both parent and child items; frontend updates on hover.
- Dedicated tab for "Drawer Settings & Footer".
- **Why Selected**: Everything is contextual. Editing a menu item immediately surfaces its visual banner and feature card. Drag-and-drop hierarchy remains fluid and intuitive.

### Approach 2: Split Multi-Tab Architecture
- Primary tree, Secondary tree, Visual Banners, and Footer separated into 4 isolated tabs.
- **Why Rejected**: Requires jumping between tabs to associate a visual banner with a menu item. Risk of broken references when menu IDs change.

---

## 5. Detailed Specifications

### 5.1 Architectural Decisions (Confirmed with User)

1. **Single Unified Responsive Menu Architecture (No Separate Mobile Tree)**:
   - As confirmed with the user, the plugin will **not** split into two separate independent trees.
   - A single unified master menu dataset (`dmm_menu_data`, migrated from `dmm_desktop_menu_data`) powers the navigation.
   - The frontend rendering engine, CSS, and JS are ported directly from `Handdn Menu Prototype.html`:
     - **Desktop (> breakpoint)**: Panels cascade horizontally side-by-side (`transform: none; left: calc(var(--lvl) * var(--panel-w))`), with dynamic right-hand editorial visual pane `#hmVis`.
     - **Mobile (<= breakpoint)**: Full-screen drill-down with smooth slide transitions (`translateX(100%) -> transform: none`), parent headers, and back buttons (`<button class="hm-back">Back</button>`).
   - This eliminates double data-entry and eliminates caching issues between desktop and mobile.

2. **Group Headings Implementation**:
   - In the Admin tree builder, a `Group Heading` (`item_type: "heading"`) acts as a visual parent container in the UI so that dragging a heading moves all its child items along with it.
   - In the frontend panel rendering engine, an item of type `heading` **does NOT create a new drill-down sub-panel**. Instead, it renders inside its parent's panel as a grouped section: `<div class="hm-group"><h4 class="st">TITLE</h4><ul class="hm-list">{children}</ul></div>`.
   - Items under a heading that have their own children (e.g. "Exotic leathers" under "BY MATERIAL") will open their own drill-down Level 2 panel as expected.

3. **`sizes_grid` (Lug Width) Panel Resolution**:
   - Items marked as `item_type: "sizes_grid"` render as a drill-down link in the parent panel.
   - When clicked, it opens a synthesized child panel containing the 4-column size button grid (14mm to 26mm) and the measurement guide link, matching prototype section `p-size`.
   - The base attribute URL (default: `/attribute/lug-width/`) and guide URL (default: `/support-center/how-to-measure-exactly-watch-strap/`) are configurable in Tab 1 of the item inspector.

4. **Trustpilot Plugin Auto-Detection & Fallback**:
   - In the "Drawer Promo & Footer" tab, there is a toggle: **"Auto-detect from Trustpilot Plugin"**.
   - When enabled, the plugin inspects active WordPress options for Trustpilot (such as `trustpilot_settings`, `trustpilot_woocommerce_settings`, or relevant transients) to fetch current rating stars, review count, and business review URL.
   - If no Trustpilot plugin is active or if auto-detect is turned off, the admin provides direct manual input fields: *Rating* (default `4.7`), *Review Count* (default `619`), and *Trustpilot Review URL*.

5. **Flatsome Theme Trigger Integration**:
   - The drawer markup is rendered in `wp_footer`.
   - The drawer opens when clicking any element with `.dmm-menu-trigger` or Flatsome's native header hamburger buttons (`.menu-button`, `.mobile-nav-trigger`).
   - Also provides a shortcode `[dmm_menu_trigger label="Menu"]` and Flatsome Header Builder element bridge.

### 5.2 Extended JSON Schema

Each menu item in `dmm_desktop_menu_data` and `dmm_mobile_menu_data`:

```json
{
  "db_id": 64533,
  "title": "Exotic leathers",
  "url": "https://handdn.us/shop/exotic-leather-watch-straps/",
  "menu_item_parent": "21395",
  "section": "primary",
  "item_type": "link",
  "tag": "Must try",
  "classes": ["label-hot"],
  "visual": {
    "enabled": true,
    "media_type": "texture",
    "image_url": "",
    "tex": "scales",
    "tone": "#3b2416",
    "kicker": "Exotic leathers",
    "headline": "440 straps in alligator, ostrich, stingray and more.",
    "cta_text": "Shop exotic leathers",
    "cta_url": "https://handdn.us/shop/exotic-leather-watch-straps/"
  },
  "feature": {
    "enabled": false,
    "media_type": "texture",
    "image_url": "",
    "tex": "scales",
    "tone": "#3b2416",
    "kicker": "Craft",
    "text": "Saddle-stitched by hand and checked three times before it ships.",
    "cta_text": "Read the process",
    "cta_url": "https://handdn.com/craftsmanship-story/"
  }
}
```

#### Field Details:
1. `section`: `'primary' | 'secondary'` (only applicable to Root items; child items automatically inherit section from their root ancestor).
2. `item_type`:
   - `'link'`: Normal clickable link.
   - `'heading'`: Section header (e.g., `BY MATERIAL`, `CONSTRUCTION`). Non-clickable in frontend (`<h4>` element), URL field disabled in admin.
   - `'sizes_grid'`: Renders a 4-column lug-width grid (14mm–26mm) with measurement guide link.
3. `tag`: Custom text badge displayed next to the label (e.g., `Must try`, `New`, `Hot`). Replaces clumsy class-based text.
4. `visual`: Configuration for the desktop right-hand editorial pane.
   - `enabled`: Boolean.
   - `media_type`: `'texture'` (canvas preset) or `'image'` (uploaded media).
   - `tex`: Texture preset (`grain`, `scales`, `dots`, `quill`, `weave`, `cross`, `smooth`).
   - `tone`: Hex color code for canvas or fallback background.
   - `kicker`, `headline`, `cta_text`, `cta_url`.
   - *Child Item Behavior*: If a child item has `visual.enabled == true`, hovering over it updates the rightmost visual pane. If false/disabled, hover keeps the parent's visual.
5. `feature`: Configuration for sub-panel bottom feature card.

### 5.3 Drawer Global Settings Schema (`dmm_settings`)

Stored in `dmm_settings`:
```json
{
  "enable_plugin": 1,
  "mobile_breakpoint": 767,
  "root_promo": {
    "enabled": true,
    "media_type": "texture",
    "image_url": "",
    "tex": "grain",
    "tone": "#8a4a26",
    "kicker": "3D Custom",
    "headline": "Design your strap in 3D, then we cut and stitch it by hand.",
    "cta_text": "Start designing",
    "cta_url": "https://handdn.com/product/3d-custom-watch-strap/",
    "show_strap_svg": true
  },
  "drawer_footer": {
    "trustpilot_auto": true,
    "trustpilot_enabled": true,
    "trustpilot_rating": "4.7",
    "trustpilot_count": "619",
    "trustpilot_url": "https://www.trustpilot.com/review/handdn.com",
    "account_link_enabled": true,
    "track_order_enabled": true,
    "contact_link_enabled": true,
    "custom_links": [
      { "label": "Instagram", "url": "https://www.instagram.com/handdn.store/" }
    ]
  }
}
```

### 5.4 Admin UI & Navigation Tabs

1. **Top Admin Navigation Tabs**:
   - Tab 1: **Menu Builder** (Cây menu điều hướng kéo thả duy nhất cho toàn hệ thống)
   - Tab 2: **Drawer Promo & Footer** (Cấu hình Banner 3D Custom ở chân Level 0, thông tin Trustpilot và liên kết tiện ích)
   - Tab 3: **General Settings** (Bật/tắt plugin, Breakpoint màn hình mobile, tích hợp Flatsome)

2. **Tree Item Header & Controls**:
   - Root items show a distinct badge: `[PRIMARY]` (blue/navy) or `[SECONDARY]` (gold/amber). Clicking it toggles the section type.
   - `Group Heading` items show a distinct badge `[HEADING]` and bold uppercase title; the URL preview is replaced by a badge indicating it is a section separator.
   - `Sizes Grid` items show a badge `[SIZES GRID 14-26mm]`.
   - Visual indicator badge `[VISUAL]` if visual metadata is configured on that item.
3. **Item Edit Drawer/Modal**:
   - Replaces the simple row dropdown with an ergonomic, structured inspector modal/drawer with 3 tabs:
     - **Tab 1: Link & General Settings**: Title, Item Type, URL, Tag/Badge, CSS classes.
     - **Tab 2: Editorial Visual Banner**: Media selector (Image upload via `wp.media` or Canvas Texture dropdown), Tone color (`wp-color-picker`), Kicker, Headline, CTA text, CTA URL.
     - **Tab 3: Sub-panel Feature Card**: Feature toggle, Kicker, Description, CTA text & URL.

---

## 6. Frontend Output & Integration Architecture

- The plugin serves two functions:
  1. Data provider: Returns the structured hierarchy via PHP helper `DMM_Menu_Data_Manager::get_nav_tree( $device )` or REST/localized JS.
  2. Frontend Drawer Renderer: Outputs the exact cascading DOM markup matching `Handdn Menu Prototype.html`:
     - Container `#hm` with `.hm-panel.lvl0`, `.hm-panel.lvl1`, etc.
     - Separated `.hm-list.primary` and `.hm-list.secondary`.
     - Group headings rendered as `<div class="hm-group"><h4>...</h4><ul class="hm-list">...</ul></div>`.
     - Sizes grid rendered as `<ul class="sizes">...</ul>`.
     - Sub-panel feature card `.feature` at bottom of panels.
     - Desktop right-side visual pane `#hmVis` dynamically reacting to panel open and item hover.
     - Drawer footer `.hm-foot` with Trustpilot stars and WooCommerce links.

---

## 7. What is Left to the Builder

- Exact styling touches for the admin modal tabs in `assets/css/admin-builder.css`.
- Seed data migration: converting existing HandDN sample dataset to the new schema with pre-populated visuals matching `Handdn Menu Prototype.html`.
- WordPress Media Uploader JS bridge (`wp.media`) for picking custom photos for visuals.
