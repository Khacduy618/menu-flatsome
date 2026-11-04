# Handdn Frontend Header & Drawer Menu Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

This plan implements the complete customer-facing Header and interactive Drawer Menu on `https://handdn.ddev.site` matching the user's mockup image and the Handdn Menu Prototype.

---

## File Structure

```
device-menu-manager/
├── includes/
│   ├── class-frontend-renderer.php    # NEW: Traverses menu tree & renders #hm, #scrim, #hmVis, #srch
│   ├── class-flatsome-integrator.php   # UPDATED: Enqueues frontend assets, binds header hamburger, hides default off-canvas
│   └── class-menu-data-manager.php     # Existing data manager providing hierarchical tree & settings
├── assets/
│   ├── css/
│   │   ├── admin-builder.css          # Existing admin styling
│   │   └── handdn-menu.css            # NEW: Frontend drawer styles + Flatsome header override styles
│   └── js/
│       ├── admin-builder.js           # Existing admin script
│       └── handdn-menu.js             # NEW: Frontend drawer interactive controller (desktop cascade, mobile drill-down, swatch painter)
├── tests/
│   ├── test-frontend-renderer.php     # NEW: Verifies HTML output of drawer markup, panels, and data attributes
│   └── test-end-to-end.php            # Existing tests
└── device-menu-manager.php            # Loads class-frontend-renderer.php
```

---

## Task 1: WordPress Media Logo Import & Flatsome Header Configuration

- [ ] **Step 1.1: Import Handdn logo image into WordPress media library**
  - Path: `/Users/khacduy/.gemini/antigravity/brain/81776241-ba35-47af-a73c-34b2975b92b5/.user_uploaded/media_1793708564344_87d7ca09.png`
  - Run `ddev wp media import` inside `/Users/khacduy/Documents/project-wp/handdn` to upload `handdn-logo.png`.
  - Capture the generated attachment ID and image URL.

- [ ] **Step 1.2: Configure Flatsome theme mods for Header & Topbar**
  - Set `site_logo` and `site_logo_dark` to the imported logo URL.
  - Set `logo_position` to `center`.
  - Set `header_elements_left` to `["menu-icon", "divider", "search"]`.
  - Set `header_elements_right` to `["cart", "divider", "account"]`.
  - Set `topbar_elements_left` to `["social"]`.
  - Set `topbar_elements_center` to `["html"]` with content: `Free shipping worldwide from $450`.
  - Set `topbar_elements_right` to `["html-2"]` with content: `<a href="/support-center/how-to-measure-exactly-watch-strap/">Size Guide</a> | <a href="/contact/">Contact Us</a> | <a href="/my-account/track-order/">Order Track</a>`.
  - Set `top_bar_bg` or top bar class to dark navy `#161735`.

- [ ] **Step 1.3: Verify Flatsome theme mods**
  - Run `ddev wp option get theme_mods_flatsome --format=json` to verify settings are saved.

---

## Task 2: Frontend Drawer HTML Renderer Class

- [ ] **Step 2.1: Write failing test `tests/test-frontend-renderer.php`**
  - Verify that `DMM_Frontend_Renderer::render()` produces:
    - `<div class="scrim" id="scrim"`
    - `<nav class="hm" id="hm"`
    - `#p-root` with `.hm-list.primary`, `.hm-list.secondary`, `.hm-promo`, `.hm-foot` with Trustpilot rating and WooCommerce links.
    - Child panels for sub-levels (`lvl1`, `lvl2`) with `.hm-top`, `.hm-back`, `.hm-close`, and group headings.
    - Sizes grid (`.sizes`) for items with `item_type: 'sizes_grid'`.
    - Feature cards (`.feature`) for nodes with feature data.
    - Aside `#hmVis` with data-layer a & b.
    - Search modal `#srch`.

- [ ] **Step 2.2: Implement `includes/class-frontend-renderer.php`**
  - Create class `DMM_Frontend_Renderer` with:
    - `public static function render(): void`
    - Helper methods: `render_root_panel()`, `render_child_panels()`, `render_groups()`, `render_item()`, `render_sizes_grid()`, `render_visual_banner()`, `render_search_modal()`.
    - Sanitizes all outputs and escapes HTML/URLs with WordPress functions (`esc_html`, `esc_url`, `esc_attr`).
    - Gathers visual configurations for all panels and items so the visual aside can display matching grain/color/photo on hover.

- [ ] **Step 2.3: Require in `device-menu-manager.php`**
  - Add `require_once DMM_PATH . 'includes/class-frontend-renderer.php';` in `Device_Menu_Manager::includes()`.

- [ ] **Step 2.4: Run test to make sure it passes**
  - Execute `php tests/test-frontend-renderer.php`.

---

## Task 3: Frontend CSS for Header & Drawer Menu

- [ ] **Step 3.1: Create `assets/css/handdn-menu.css`**
  - Integrate prototype design tokens:
    - `--paper: #ffffff`, `--ink: #1b1714`, `--muted: #716a62`, `--line: #e8e4de`, `--wash: #f6f4f1`, `--scrim: rgba(27,23,20,.36)`.
    - Typography: Google Font `Jost`, smooth antialiasing.
  - Multi-panel geometry:
    - `--panel-w: clamp(320px, 27vw, 392px)` on desktop; `100vw` on mobile (<768px).
    - Transitions: `cubic-bezier(.16,1,.3,1)` for smooth slide and cascade.
  - Header styling overrides to match mockup:
    - `#top-bar`: Background `#161735`, color `#ffffff`, font size 12px, clean spacing.
    - `#masthead.header-main`: White background, height ~72px, border-bottom 1px solid `#e8e4de`.
    - Menu button: Hamburger icon + "MENU" text in 13px uppercase tracking.
    - Vertical divider: 1px solid `#e8e4de`, height 24px.
    - Search button: 40x40px rounded box border `#e8e4de` with search icon centered.
    - Logo: Centered and sized proportionally.
    - Right cart & account: `Cart / $0.00 [Cart Icon]` and `Login / Register` with clean typography.
  - Drawer components:
    - Panels: `.hm-panel`, `.hm-panel.open`, `.hm-panel.behind` (mobile drill-down).
    - Promo banner: `.hm-promo` with SVG strap outline, kicker, headline, CTA button.
    - Feature cards: `.feature` with preview thumbnail and kicker/text/link.
    - Sizes grid: `.sizes` 14–26mm 4-column grid.
    - Trustpilot footer: `.tp` with 5 stars, rating score, reviews count.
    - Visual pane: `#hmVis.hm-visual` positioned to the right of active panels on desktop.
    - Search modal: `#srch.srch` sliding from the top.

---

## Task 4: Frontend JS Controller & Procedural Swatch Painter

- [ ] **Step 4.1: Create `assets/js/handdn-menu.js`**
  - Encapsulate in IIFE: `(() => { ... })();`.
  - State variables: `isOpen`, `stack = []`, `visKey`, `front`.
  - Open / Close functions:
    - Binds click on `.dmm-menu-btn`, `[data-open="#main-menu"]`, `.menu-icon a`, `.hbtn#menuBtn`.
    - Toggles `body.hm-lock`, `.scrim.on`, `.hm.on`.
    - Traps focus and returns focus on close.
  - Navigation:
    - Desktop: Clicking or hovering sub-menu triggers opens cascading panel next to current panel.
    - Mobile: Clicking sub-menu trigger slides next panel in, marks current panel `.behind`.
    - Back button: Pops stack and slides previous panel back.
    - Close button / Scrim click: Closes entire drawer.
    - Keyboard: ESC pops stack or closes drawer; arrow keys navigate list.
  - Visual Banner Swapping:
    - `showVisual(panelIdOrNode)`: Updates rightmost pane `#hmVis` with matching texture canvas, background color tone, kicker, title, and CTA link.
    - Leaf hover: Hovering leather items updates the visual swatch dynamically.
  - Procedural Canvas Swatch Painter:
    - Includes `swatch(tex, tone, w, h)` supporting `scales`, `dots`, `quill`, `weave`, `cross`, `smooth`, and `grain` pebble textures with saddle-stitched line overlay.
    - Supports real image overrides when `data-img` is present.
  - Search Modal:
    - Binds search button to slide down `#srch`.

- [ ] **Step 4.2: Verify JS syntax**
  - Run `node -c assets/js/handdn-menu.js`.

---

## Task 5: Flatsome Theme Integration Bridge

- [ ] **Step 5.1: Update `includes/class-flatsome-integrator.php`**
  - Enqueue Google Font `Jost` (`https://fonts.googleapis.com/css2?family=Jost:wght@300;400;500&display=swap`).
  - Enqueue `assets/css/handdn-menu.css` on frontend.
  - Enqueue `assets/js/handdn-menu.js` on frontend with `wp_localize_script` passing visual configuration and store URLs.
  - Hook `DMM_Frontend_Renderer::render` into `wp_footer`.
  - Add filter to disable Flatsome's default off-canvas menu output or intercept `[data-open="#main-menu"]` to prevent conflict.
  - Filter Flatsome header search and menu markup so that:
    - Hamburger button has text "MENU".
    - Search button opens `#srch` or has square box class `.dmm-search-box`.

- [ ] **Step 5.2: Test PHP syntax**
  - Run `php -l includes/class-flatsome-integrator.php`.

---

## Task 6: Sync to DDEV & Automated Verification via Chrome DevTools MCP

- [ ] **Step 6.1: Sync codebase to DDEV site**
  - Run `rsync -av /Users/khacduy/Documents/project-wp/device-menu-manager/ /Users/khacduy/Documents/project-wp/handdn/wp-content/plugins/device-menu-manager/`.

- [ ] **Step 6.2: Verify PHP tests in DDEV container**
  - Run `ddev exec "php /var/www/html/wp-content/plugins/device-menu-manager/tests/test-frontend-renderer.php"`.

- [ ] **Step 6.3: Chrome DevTools MCP Verification**
  - MCP `navigate_page` to `https://handdn.ddev.site`.
  - MCP `take_screenshot` of the new Header layout (verify topbar `#161735`, MENU button, search box, centered logo, cart & login).
  - MCP `click` on the MENU button.
  - MCP `take_screenshot` of the opened Desktop Drawer Menu (verify root panel, promo card, visual banner).
  - MCP `hover` or `click` on "Watch Straps" -> "Exotic leathers" -> verify cascading panel opens and visual banner updates.
  - MCP `resize_page` to mobile (375x812).
  - MCP `click` on MENU button, click "Watch Straps", verify mobile drill-down, click "Back" button.
  - MCP `take_screenshot` on mobile view.

---

## Task 7: Final Review & Polish
- [ ] Ensure all visual details match the user's mockup.
- [ ] Confirm zero browser console errors and zero PHP warnings.
