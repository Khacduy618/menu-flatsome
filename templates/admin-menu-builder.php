<?php
/**
 * Modern Minimalist Admin Menu Builder Template (Shadcn UI style).
 *
 * @package Device_Menu_Manager
 */

defined( 'ABSPATH' ) || exit;

$settings        = get_option( 'dmm_settings', array() );
$drawer_settings = DMM_Menu_Data_Manager::get_drawer_settings();
$root_promo      = $settings['root_promo'] ?? array();
$drawer_footer   = $settings['drawer_footer'] ?? array();

$promo_enabled   = isset( $root_promo['enabled'] ) ? ! empty( $root_promo['enabled'] ) : ( ! empty( $drawer_settings['promo_enabled'] ) );
$promo_media     = $root_promo['media_type'] ?? ( $drawer_settings['promo_media_type'] ?? 'upload' );
if ( 'url' !== $promo_media ) {
	$promo_media = 'upload';
}
$promo_tex       = $root_promo['tex'] ?? ( $drawer_settings['promo_texture'] ?? 'grain' );
$promo_tone      = ! empty( $root_promo['tone'] ) ? $root_promo['tone'] : ( $drawer_settings['promo_tone'] ?? '#8a4a26' );
$promo_image_url = $root_promo['image_url'] ?? ( $drawer_settings['promo_image_url'] ?? '' );
$promo_kicker    = ( isset( $root_promo['kicker'] ) && '' !== $root_promo['kicker'] ) ? $root_promo['kicker'] : ( $drawer_settings['promo_kicker'] ?? '3D Custom' );
$promo_headline  = ( isset( $root_promo['headline'] ) && '' !== $root_promo['headline'] ) ? $root_promo['headline'] : ( $drawer_settings['promo_headline'] ?? 'Design your strap in 3D, then we cut and stitch it by hand.' );
$promo_cta_text  = ( isset( $root_promo['cta_text'] ) && '' !== $root_promo['cta_text'] ) ? $root_promo['cta_text'] : ( $drawer_settings['promo_cta_text'] ?? 'Start designing' );
$promo_cta_url   = ( isset( $root_promo['cta_url'] ) && '' !== $root_promo['cta_url'] ) ? $root_promo['cta_url'] : ( $drawer_settings['promo_cta_url'] ?? 'https://handdn.us/product/3d-custom-watch-strap/' );
$promo_svg       = isset( $root_promo['show_strap_svg'] ) ? ! empty( $root_promo['show_strap_svg'] ) : ( ! empty( $drawer_settings['promo_show_strap'] ) );

$tp_enabled      = isset( $drawer_footer['trustpilot_enabled'] ) ? ! empty( $drawer_footer['trustpilot_enabled'] ) : ( ! empty( $drawer_settings['trustpilot_enabled'] ) );
$tp_auto         = isset( $drawer_footer['trustpilot_auto'] ) ? ! empty( $drawer_footer['trustpilot_auto'] ) : ( ! empty( $drawer_settings['trustpilot_auto'] ) );
$tp_rating       = ! empty( $drawer_footer['trustpilot_rating'] ) ? $drawer_footer['trustpilot_rating'] : ( $drawer_settings['trustpilot_rating'] ?? '4.7' );
$tp_count        = ! empty( $drawer_footer['trustpilot_count'] ) ? $drawer_footer['trustpilot_count'] : ( $drawer_settings['trustpilot_count'] ?? '619' );
$tp_url          = ! empty( $drawer_footer['trustpilot_url'] ) ? $drawer_footer['trustpilot_url'] : ( $drawer_settings['trustpilot_url'] ?? 'https://www.trustpilot.com/review/handdn.com' );
$account_enabled = isset( $drawer_footer['account_link_enabled'] ) ? ! empty( $drawer_footer['account_link_enabled'] ) : ( ! empty( $drawer_settings['account_link_enabled'] ) );
$track_enabled   = isset( $drawer_footer['track_order_enabled'] ) ? ! empty( $drawer_footer['track_order_enabled'] ) : ( ! empty( $drawer_settings['track_order_enabled'] ) );
$contact_enabled = isset( $drawer_footer['contact_link_enabled'] ) ? ! empty( $drawer_footer['contact_link_enabled'] ) : ( ! empty( $drawer_settings['contact_link_enabled'] ) );
$instagram_url   = ! empty( $drawer_footer['instagram_url'] ) ? $drawer_footer['instagram_url'] : ( $drawer_settings['wc_instagram_url'] ?? 'https://www.instagram.com/handdn.store/' );
$tp_status       = DMM_Menu_Data_Manager::get_trustpilot_status();
?>

<div class="wrap dmm-admin-wrap">
	<!-- Shadcn Header -->
	<header class="dmm-header">
		<div class="dmm-header-brand">
			<div class="dmm-brand-icon">
				<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
					<path d="m12.83 2.18a2 2 0 0 0-1.66 0L2.6 6.08a1 1 0 0 0 0 1.83l8.58 3.91a2 2 0 0 0 1.66 0l8.58-3.9a1 1 0 0 0 0-1.83Z"/>
					<path d="m22 17.65-9.17 4.16a2 2 0 0 1-1.66 0L2 17.65"/>
					<path d="m22 12.65-9.17 4.16a2 2 0 0 1-1.66 0L2 12.65"/>
				</svg>
			</div>
			<div>
				<h1 class="dmm-title"><?php esc_html_e( 'Handdn Navigation Manager', 'device-menu-manager' ); ?></h1>
				<p class="dmm-subtitle"><?php esc_html_e( 'Manage cascading navigation, dynamic visual banners, and mobile drawer.', 'device-menu-manager' ); ?></p>
			</div>
		</div>
		<div class="dmm-header-actions">
			<button type="button" class="dmm-btn dmm-btn-outline dmm-btn-reset-sample" data-device="main" title="<?php esc_attr_e( 'Reset to default Handdn presets with luxury visuals', 'device-menu-manager' ); ?>">
				<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg>
				<span><?php esc_html_e( 'Reset Presets', 'device-menu-manager' ); ?></span>
			</button>
			<button type="button" id="dmm-global-save-btn" class="dmm-btn dmm-btn-primary dmm-btn-save">
				<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
				<span class="dmm-btn-label"><?php esc_html_e( 'Save Changes', 'device-menu-manager' ); ?></span>
			</button>
		</div>
	</header>

	<!-- Notification Toast -->
	<div id="dmm-toast" class="dmm-toast" style="display: none;">
		<span class="dmm-toast-icon"></span>
		<span class="dmm-toast-message"></span>
	</div>

	<!-- Main Navigation Tabs (Shadcn Segmented Bar) -->
	<nav class="dmm-tabs-nav">
		<button type="button" class="dmm-tab-trigger active" data-tab="builder">
			<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="4" x2="20" y1="12" y2="12"/><line x1="4" x2="20" y1="6" y2="6"/><line x1="4" x2="20" y1="18" y2="18"/></svg>
			<span><?php esc_html_e( 'Menu Tree & Visuals', 'device-menu-manager' ); ?></span>
		</button>
		<button type="button" class="dmm-tab-trigger" data-tab="drawer">
			<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="3" rx="2" ry="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg>
			<span><?php esc_html_e( 'Root Promo & Footer', 'device-menu-manager' ); ?></span>
		</button>
		<button type="button" class="dmm-tab-trigger" data-tab="settings">
			<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/></svg>
			<span><?php esc_html_e( 'General Settings', 'device-menu-manager' ); ?></span>
		</button>
	</nav>

	<!-- Tab Panels -->
	<div class="dmm-tab-contents">

		<!-- Tab 1: Menu Tree & Visuals -->
		<div id="dmm-tab-builder" class="dmm-tab-panel active" data-device="main">
			<div class="dmm-builder-wrapper">
				
				<!-- Action Toolbar -->
				<div class="dmm-toolbar">
					<div class="dmm-toolbar-group">
						<button type="button" class="dmm-btn dmm-btn-sm dmm-btn-primary dmm-btn-add-item" data-type="link">
							<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
							<span><?php esc_html_e( 'Add Link', 'device-menu-manager' ); ?></span>
						</button>
						<button type="button" class="dmm-btn dmm-btn-sm dmm-btn-outline dmm-btn-add-heading" data-type="heading">
							<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 12h12"/><path d="M6 4v16"/><path d="M18 4v16"/></svg>
							<span><?php esc_html_e( 'Add Heading', 'device-menu-manager' ); ?></span>
						</button>
						<button type="button" class="dmm-btn dmm-btn-sm dmm-btn-outline dmm-btn-add-sizes" data-type="sizes_grid">
							<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="7" height="7" x="3" y="3" rx="1"/><rect width="7" height="7" x="14" y="3" rx="1"/><rect width="7" height="7" x="14" y="14" rx="1"/><rect width="7" height="7" x="3" y="14" rx="1"/></svg>
							<span><?php esc_html_e( 'Add Sizes Grid', 'device-menu-manager' ); ?></span>
						</button>
					</div>

					<div class="dmm-toolbar-group">
						<div class="dmm-import-wp-group">
							<select class="dmm-input-select dmm-import-select" id="dmm-import-select-main">
								<option value=""><?php esc_html_e( 'Import from WP...', 'device-menu-manager' ); ?></option>
								<?php if ( ! empty( $wp_menus ) ) : ?>
									<?php foreach ( $wp_menus as $menu_obj ) : ?>
										<option value="<?php echo esc_attr( $menu_obj->term_id ); ?>"><?php echo esc_html( $menu_obj->name ); ?></option>
									<?php endforeach; ?>
								<?php endif; ?>
							</select>
							<button type="button" class="dmm-btn dmm-btn-sm dmm-btn-outline dmm-btn-import-wp" data-device="main">
								<?php esc_html_e( 'Import', 'device-menu-manager' ); ?>
							</button>
						</div>

						<div class="dmm-btn-group">
							<button type="button" class="dmm-btn dmm-btn-sm dmm-btn-ghost dmm-btn-collapse-all" data-device="main" title="<?php esc_attr_e( 'Collapse all sub-menus', 'device-menu-manager' ); ?>">
								<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
								<span><?php esc_html_e( 'Collapse', 'device-menu-manager' ); ?></span>
							</button>
							<button type="button" class="dmm-btn dmm-btn-sm dmm-btn-ghost dmm-btn-expand-all" data-device="main" title="<?php esc_attr_e( 'Expand all sub-menus', 'device-menu-manager' ); ?>">
								<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
								<span><?php esc_html_e( 'Expand', 'device-menu-manager' ); ?></span>
							</button>
						</div>

						<button type="button" class="dmm-btn dmm-btn-sm dmm-btn-ghost dmm-btn-toggle-json" data-device="main" title="<?php esc_attr_e( 'Raw JSON Editor', 'device-menu-manager' ); ?>">
							<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>
							<span><?php esc_html_e( 'JSON', 'device-menu-manager' ); ?></span>
						</button>

						<span class="dmm-item-count-badge" id="dmm-count-main">0 items</span>
					</div>
				</div>

				<!-- Raw JSON Panel (Collapsible) -->
				<div class="dmm-json-panel" id="dmm-json-panel-main" style="display: none;">
					<div class="dmm-json-header">
						<h4><?php esc_html_e( 'Raw JSON Schema (Direct Export / Import)', 'device-menu-manager' ); ?></h4>
						<p><?php esc_html_e( 'Direct JSON synchronization of full hierarchical tree, visual banners, and presets.', 'device-menu-manager' ); ?></p>
					</div>
					<textarea id="dmm-json-textarea-main" class="dmm-json-textarea" rows="10" spellcheck="false"></textarea>
					<div class="dmm-json-actions">
						<button type="button" class="dmm-btn dmm-btn-sm dmm-btn-primary dmm-btn-apply-json" data-device="main">
							<?php esc_html_e( 'Apply JSON to Tree', 'device-menu-manager' ); ?>
						</button>
						<button type="button" class="dmm-btn dmm-btn-sm dmm-btn-outline dmm-btn-copy-json" data-device="main">
							<?php esc_html_e( 'Copy to Clipboard', 'device-menu-manager' ); ?>
						</button>
						<button type="button" class="dmm-btn dmm-btn-sm dmm-btn-ghost dmm-btn-close-json" data-device="main">
							<?php esc_html_e( 'Close', 'device-menu-manager' ); ?>
						</button>
					</div>
				</div>

				<!-- Menu Tree Container -->
				<div class="dmm-tree-container">
					<div class="dmm-tree-hint">
						<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
						<span><?php esc_html_e( 'Drag items horizontally to change hierarchy depth. Click "Edit" or "Visual" to customize details and dynamic right-hand visual banners.', 'device-menu-manager' ); ?></span>
					</div>

					<ul class="dmm-nested-sortable" id="dmm-tree-main" data-device="main">
						<!-- Populated dynamically via admin-builder.js -->
					</ul>

					<div class="dmm-tree-empty" id="dmm-empty-main" style="display: none;">
						<p><?php esc_html_e( 'No menu items found. Click "Add Link" or "Reset Presets" to start.', 'device-menu-manager' ); ?></p>
					</div>
				</div>

			</div>
		</div>

		<!-- Tab 2: Root Promo & Footer -->
		<div id="dmm-tab-drawer" class="dmm-tab-panel" data-device="drawer">
			
			<!-- Card 1: Root Promo Banner -->
			<div class="dmm-card">
				<div class="dmm-card-header">
					<div>
						<h3 class="dmm-card-title"><?php esc_html_e( 'Root Level 0 Promo Banner', 'device-menu-manager' ); ?></h3>
						<p class="dmm-card-description"><?php esc_html_e( 'Displays at the bottom of the root drawer panel (e.g. 3D Custom Strap designer).', 'device-menu-manager' ); ?></p>
					</div>
					<label class="dmm-switch">
						<input type="checkbox" id="dmm-promo-enabled" <?php checked( $promo_enabled ); ?>>
						<span class="dmm-switch-slider"></span>
					</label>
				</div>

				<div class="dmm-card-body">
					<div class="dmm-grid-2">
						<div class="dmm-form-group">
							<label class="dmm-label"><?php esc_html_e( 'Media Type', 'device-menu-manager' ); ?></label>
							<select id="dmm-promo-media-type" class="dmm-input-select">
								<option value="upload" <?php selected( $promo_media, 'upload' ); ?>><?php esc_html_e( 'Upload Image', 'device-menu-manager' ); ?></option>
								<option value="url" <?php selected( $promo_media, 'url' ); ?>><?php esc_html_e( 'Image URL', 'device-menu-manager' ); ?></option>
							</select>
						</div>
					</div>

					<div class="dmm-form-group dmm-promo-upload-row" style="<?php echo ( 'url' === $promo_media ) ? 'display: none;' : ''; ?>">
						<label class="dmm-label"><?php esc_html_e( 'Select Image from Media Library', 'device-menu-manager' ); ?></label>
						<div>
							<button type="button" class="dmm-btn dmm-btn-outline dmm-btn-select-media" data-prefix="promo">
								<span class="dashicons dashicons-upload" style="vertical-align: middle; margin-right: 4px; font-size: 16px; width: 16px; height: 16px;"></span><?php esc_html_e( 'Upload / Select Image', 'device-menu-manager' ); ?>
							</button>
						</div>
					</div>

					<div class="dmm-form-group dmm-promo-url-row" style="<?php echo ( 'url' === $promo_media ) ? '' : 'display: none;'; ?>">
						<label class="dmm-label"><?php esc_html_e( 'Direct Image URL', 'device-menu-manager' ); ?></label>
						<input type="text" id="dmm-promo-image-url-input" class="dmm-input" value="<?php echo esc_attr( $promo_image_url ); ?>" placeholder="https://handdn.us/wp-content/uploads/...">
					</div>

					<input type="hidden" id="dmm-promo-image-url" value="<?php echo esc_attr( $promo_image_url ); ?>">

					<div class="dmm-form-group dmm-promo-preview-wrap" style="<?php echo empty( $promo_image_url ) ? 'display: none;' : ''; ?>">
						<label class="dmm-label"><?php esc_html_e( 'Image Preview', 'device-menu-manager' ); ?></label>
						<div class="dmm-media-preview dmm-promo-preview">
							<img src="<?php echo esc_url( $promo_image_url ); ?>" alt="Preview">
							<button type="button" class="dmm-btn dmm-btn-ghost dmm-btn-remove-media" data-prefix="promo"><?php esc_html_e( 'Remove', 'device-menu-manager' ); ?></button>
						</div>
					</div>

					<div class="dmm-grid-2">
						<div class="dmm-form-group">
							<label class="dmm-label"><?php esc_html_e( 'Kicker (Eyebrow)', 'device-menu-manager' ); ?></label>
							<input type="text" id="dmm-promo-kicker" class="dmm-input" value="<?php echo esc_attr( $promo_kicker ); ?>">
						</div>
						<div class="dmm-form-group">
							<label class="dmm-label"><?php esc_html_e( 'Watch Strap SVG Diagram', 'device-menu-manager' ); ?></label>
							<label class="dmm-checkbox-label" style="margin-top: 8px;">
								<input type="checkbox" id="dmm-promo-svg" <?php checked( $promo_svg ); ?>>
								<span><?php esc_html_e( 'Overlay technical watch strap outline', 'device-menu-manager' ); ?></span>
							</label>
						</div>
					</div>

					<div class="dmm-form-group">
						<label class="dmm-label"><?php esc_html_e( 'Headline', 'device-menu-manager' ); ?></label>
						<textarea id="dmm-promo-headline" class="dmm-input dmm-textarea" rows="2"><?php echo esc_textarea( $promo_headline ); ?></textarea>
					</div>

					<div class="dmm-grid-2">
						<div class="dmm-form-group">
							<label class="dmm-label"><?php esc_html_e( 'CTA Text', 'device-menu-manager' ); ?></label>
							<input type="text" id="dmm-promo-cta-text" class="dmm-input" value="<?php echo esc_attr( $promo_cta_text ); ?>">
						</div>
						<div class="dmm-form-group">
							<label class="dmm-label"><?php esc_html_e( 'CTA Link URL', 'device-menu-manager' ); ?></label>
							<input type="text" id="dmm-promo-cta-url" class="dmm-input" value="<?php echo esc_attr( $promo_cta_url ); ?>">
						</div>
					</div>
				</div>
			</div>

			<!-- Card 2: Trustpilot Rating -->
			<div class="dmm-card" style="margin-top: 20px;">
				<div class="dmm-card-header">
					<div>
						<h3 class="dmm-card-title"><?php esc_html_e( 'Trustpilot Rating & Reviews', 'device-menu-manager' ); ?></h3>
						<p class="dmm-card-description"><?php esc_html_e( 'Displays five stars rating score and review count in the drawer footer.', 'device-menu-manager' ); ?></p>
					</div>
					<label class="dmm-switch">
						<input type="checkbox" id="dmm-tp-enabled" <?php checked( $tp_enabled ); ?>>
						<span class="dmm-switch-slider"></span>
					</label>
				</div>

				<div class="dmm-card-body">
					<div class="dmm-grid-2">
						<div class="dmm-form-group">
							<label class="dmm-label"><?php esc_html_e( 'Auto-detect from Trustpilot Plugin', 'device-menu-manager' ); ?></label>
							<label class="dmm-checkbox-label" style="margin-top: 8px;">
								<input type="checkbox" id="dmm-tp-auto" <?php checked( $tp_auto ); ?>>
								<span><?php esc_html_e( 'Fetch live score automatically if Trustpilot plugin is active', 'device-menu-manager' ); ?></span>
							</label>

							<div class="dmm-tp-status-notice <?php echo $tp_status['connected'] ? 'dmm-notice-connected' : 'dmm-notice-disconnected'; ?>" id="dmm-tp-status-notice" style="<?php echo $tp_auto ? 'display: flex;' : 'display: none;'; ?> margin-top: 10px; padding: 10px 14px; border-radius: 6px; font-size: 13px; line-height: 1.45; align-items: flex-start; gap: 8px; <?php echo $tp_status['connected'] ? 'background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534;' : 'background: #fffbeb; border: 1px solid #fde68a; color: #92400e;'; ?>">
								<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink: 0; margin-top: 2px;">
									<?php if ( $tp_status['connected'] ) : ?>
										<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
									<?php else : ?>
										<path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
									<?php endif; ?>
								</svg>
								<div class="dmm-tp-status-msg" id="dmm-tp-status-msg">
									<?php if ( $tp_status['connected'] ) : ?>
										<strong><?php esc_html_e( 'Đã kết nối plugin Trustpilot', 'device-menu-manager' ); ?></strong> &mdash; <?php echo esc_html( sprintf( __( 'Score: %1$s ★ (%2$s reviews)', 'device-menu-manager' ), $tp_status['rating'], $tp_status['count'] ) ); ?>
									<?php else : ?>
										<strong><?php esc_html_e( 'Chưa kết nối được với plugin Trustpilot:', 'device-menu-manager' ); ?></strong>
										<span><?php esc_html_e( 'Không tìm thấy plugin Trustpilot đang hoạt động hoặc chưa cấu hình API. Hệ thống sẽ tự động sử dụng điểm số nhập thủ công bên cạnh.', 'device-menu-manager' ); ?></span>
									<?php endif; ?>
								</div>
							</div>
						</div>
						<div class="dmm-form-group dmm-tp-manual-row">
							<label class="dmm-label"><?php esc_html_e( 'Manual Score & Review Count', 'device-menu-manager' ); ?></label>
							<div class="dmm-inline-fields">
								<input type="text" id="dmm-tp-rating" class="dmm-input" placeholder="4.7" value="<?php echo esc_attr( $tp_rating ); ?>" style="width: 100px;">
								<input type="text" id="dmm-tp-count" class="dmm-input" placeholder="619" value="<?php echo esc_attr( $tp_count ); ?>" style="width: 130px;">
							</div>
						</div>
					</div>

					<div class="dmm-form-group">
						<label class="dmm-label"><?php esc_html_e( 'Trustpilot Review URL', 'device-menu-manager' ); ?></label>
						<input type="text" id="dmm-tp-url" class="dmm-input" value="<?php echo esc_attr( $tp_url ); ?>">
					</div>
				</div>
			</div>

			<!-- Card 3: Footer Utility Links -->
			<div class="dmm-card" style="margin-top: 20px;">
				<div class="dmm-card-header">
					<div>
						<h3 class="dmm-card-title"><?php esc_html_e( 'WooCommerce & Service Links', 'device-menu-manager' ); ?></h3>
						<p class="dmm-card-description"><?php esc_html_e( 'Quick links displayed below the Trustpilot rating.', 'device-menu-manager' ); ?></p>
					</div>
				</div>

				<div class="dmm-card-body">
					<div class="dmm-grid-2">
						<label class="dmm-checkbox-label">
							<input type="checkbox" id="dmm-foot-account" <?php checked( $account_enabled ); ?>>
							<span><?php esc_html_e( 'Sign in / My Account Link', 'device-menu-manager' ); ?></span>
						</label>
						<label class="dmm-checkbox-label">
							<input type="checkbox" id="dmm-foot-track" <?php checked( $track_enabled ); ?>>
							<span><?php esc_html_e( 'Track Order Link', 'device-menu-manager' ); ?></span>
						</label>
						<label class="dmm-checkbox-label">
							<input type="checkbox" id="dmm-foot-contact" <?php checked( $contact_enabled ); ?>>
							<span><?php esc_html_e( 'Contact Us Link', 'device-menu-manager' ); ?></span>
						</label>
						<div class="dmm-form-group" style="margin-bottom: 0;">
							<label class="dmm-label"><?php esc_html_e( 'Instagram Profile URL', 'device-menu-manager' ); ?></label>
							<input type="text" id="dmm-foot-instagram" class="dmm-input" value="<?php echo esc_attr( $instagram_url ); ?>">
						</div>
					</div>
				</div>
			</div>

		</div>

		<!-- Tab 3: General Settings -->
		<div id="dmm-tab-settings" class="dmm-tab-panel" data-device="settings">
			<div class="dmm-card">
				<div class="dmm-card-header">
					<div>
						<h3 class="dmm-card-title"><?php esc_html_e( 'Plugin Settings & Breakpoints', 'device-menu-manager' ); ?></h3>
						<p class="dmm-card-description"><?php esc_html_e( 'Master control, mobile breakpoint threshold, and trigger selectors.', 'device-menu-manager' ); ?></p>
					</div>
				</div>

				<div class="dmm-card-body">
					<div class="dmm-form-group">
						<div style="display: flex; justify-content: space-between; align-items: center;">
							<div>
								<label class="dmm-label" style="margin-bottom: 2px;"><?php esc_html_e( 'Master Plugin Toggle', 'device-menu-manager' ); ?></label>
								<p class="dmm-card-description"><?php esc_html_e( 'Turn off to disable menu drawer overrides completely.', 'device-menu-manager' ); ?></p>
							</div>
							<label class="dmm-switch">
								<input type="checkbox" id="dmm-master-toggle" <?php checked( ! empty( $settings['enable_plugin'] ) ); ?>>
								<span class="dmm-switch-slider"></span>
							</label>
						</div>
					</div>

					<div class="dmm-form-group" style="margin-top: 18px; border-top: 1px solid var(--dmm-border); padding-top: 18px;">
						<label class="dmm-label"><?php esc_html_e( 'Mobile Breakpoint (px)', 'device-menu-manager' ); ?></label>
						<div class="dmm-inline-fields">
							<input type="number" id="dmm-mobile-breakpoint" class="dmm-input" value="<?php echo esc_attr( $settings['mobile_breakpoint'] ?? 767 ); ?>" min="320" max="1440" step="1" style="width: 120px;">
							<span style="color: var(--dmm-muted-text); font-size: 13px;">px</span>
						</div>
						<p class="dmm-card-description" style="margin-top: 4px;"><?php esc_html_e( 'Viewport width threshold separating Desktop cascading view and Mobile full-screen drill-down drawer. Defaults to 767px.', 'device-menu-manager' ); ?></p>
					</div>

					<div class="dmm-form-group" style="margin-top: 18px; border-top: 1px solid var(--dmm-border); padding-top: 18px;">
						<label class="dmm-label"><?php esc_html_e( 'Menu Drawer Shortcode', 'device-menu-manager' ); ?></label>
						<code class="dmm-code">[dmm_menu_trigger label="Menu"]</code>
						<p class="dmm-card-description" style="margin-top: 4px;"><?php esc_html_e( 'Or add class "dmm-hamburger-btn" or "dmm-menu-trigger" to any custom theme button.', 'device-menu-manager' ); ?></p>
					</div>
				</div>
			</div>
		</div>

	</div>

	<!-- SHADCN SLIDE-OVER SHEET INSPECTOR -->
	<div id="dmm-inspector-sheet" class="dmm-sheet-overlay" style="display: none;">
		<div class="dmm-sheet">
			<!-- Sheet Header -->
			<div class="dmm-sheet-header">
				<div class="dmm-sheet-header-title">
					<h3 id="dmm-sheet-item-title-preview">Item Title</h3>
					<span class="dmm-sheet-badge" id="dmm-sheet-item-depth-preview">Level 1</span>
				</div>
				<button type="button" class="dmm-btn dmm-btn-ghost dmm-sheet-close-btn" title="Close">
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
				</button>
			</div>

			<!-- Sheet Sub-Tabs -->
			<nav class="dmm-sheet-tabs">
				<button type="button" class="dmm-sheet-tab-btn active" data-tab="content">
					<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
					<span><?php esc_html_e( 'Content', 'device-menu-manager' ); ?></span>
				</button>
				<button type="button" class="dmm-sheet-tab-btn" data-tab="visual">
					<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="3" rx="2" ry="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg>
					<span><?php esc_html_e( 'Visual Banner', 'device-menu-manager' ); ?></span>
				</button>
				<button type="button" class="dmm-sheet-tab-btn" data-tab="feature">
					<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="3" rx="2"/><path d="M7 7h10"/><path d="M7 12h10"/><path d="M7 17h6"/></svg>
					<span><?php esc_html_e( 'Feature Card', 'device-menu-manager' ); ?></span>
				</button>
			</nav>

			<!-- Sheet Body -->
			<div class="dmm-sheet-body">
				<!-- Tab 1: Content -->
				<div class="dmm-sheet-panel active" id="dmm-insp-panel-content">
					<div class="dmm-form-group">
						<label class="dmm-label"><?php esc_html_e( 'Label / Title', 'device-menu-manager' ); ?></label>
						<input type="text" id="dmm-insp-title" class="dmm-input" placeholder="e.g. Watch Straps, Exotic leathers...">
					</div>

					<div class="dmm-grid-2">
						<div class="dmm-form-group">
							<label class="dmm-label"><?php esc_html_e( 'Item Type', 'device-menu-manager' ); ?></label>
							<select id="dmm-insp-type" class="dmm-input-select">
								<option value="link"><?php esc_html_e( 'Standard Link', 'device-menu-manager' ); ?></option>
								<option value="heading"><?php esc_html_e( 'Group Heading', 'device-menu-manager' ); ?></option>
								<option value="sizes_grid"><?php esc_html_e( 'Grid Layout (Multi-column pills)', 'device-menu-manager' ); ?></option>
							</select>
							<p class="dmm-card-description" id="dmm-insp-type-desc" style="margin-top: 4px;"></p>
						</div>

						<div class="dmm-form-group" id="dmm-insp-section-group">
							<label class="dmm-label"><?php esc_html_e( 'Root Section', 'device-menu-manager' ); ?></label>
							<select id="dmm-insp-section" class="dmm-input-select">
								<option value="primary"><?php esc_html_e( 'Primary (Large Font)', 'device-menu-manager' ); ?></option>
								<option value="secondary"><?php esc_html_e( 'Secondary (Muted Font)', 'device-menu-manager' ); ?></option>
							</select>
						</div>
					</div>

					<div class="dmm-form-group" id="dmm-insp-url-group">
						<label class="dmm-label"><?php esc_html_e( 'Target URL', 'device-menu-manager' ); ?></label>
						<input type="text" id="dmm-insp-url" class="dmm-input" placeholder="https://handdn.us/shop/...">
					</div>

					<div class="dmm-grid-2">
						<div class="dmm-form-group">
							<label class="dmm-label"><?php esc_html_e( 'Badge / Tag', 'device-menu-manager' ); ?></label>
							<input type="text" id="dmm-insp-tag" class="dmm-input" placeholder="e.g. Must try, Hot, 20% Off...">
						</div>
						<div class="dmm-form-group">
							<label class="dmm-label"><?php esc_html_e( 'CSS Classes', 'device-menu-manager' ); ?></label>
							<input type="text" id="dmm-insp-classes" class="dmm-input" placeholder="e.g. featured-link">
						</div>
					</div>
				</div>

				<!-- Tab 2: Visual Banner -->
				<div class="dmm-sheet-panel" id="dmm-insp-panel-visual">
					<div class="dmm-callout">
						<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
						<span><?php esc_html_e( 'Configures the dynamic right-hand visual pane. When hovered, the right pane updates to this content.', 'device-menu-manager' ); ?></span>
					</div>

					<div class="dmm-form-group">
						<div style="display: flex; justify-content: space-between; align-items: center; padding: 10px 14px; background: var(--dmm-muted-bg); border-radius: var(--dmm-radius);">
							<div>
								<span style="font-size: 13px; font-weight: 600; color: var(--dmm-text);"><?php esc_html_e( 'Enable Right-hand Visual Banner', 'device-menu-manager' ); ?></span>
								<p style="margin: 2px 0 0; font-size: 12px; color: var(--dmm-muted-text);"><?php esc_html_e( 'Display custom texture or photo in rightmost pane on hover', 'device-menu-manager' ); ?></p>
							</div>
							<label class="dmm-switch">
								<input type="checkbox" id="dmm-insp-visual-enabled">
								<span class="dmm-switch-slider"></span>
							</label>
						</div>
					</div>

					<div id="dmm-insp-visual-fields" style="display: none;">
						<div class="dmm-form-group">
							<label class="dmm-label"><?php esc_html_e( 'Media Type', 'device-menu-manager' ); ?></label>
							<select id="dmm-insp-visual-media-type" class="dmm-input-select">
								<option value="upload"><?php esc_html_e( 'Upload Image', 'device-menu-manager' ); ?></option>
								<option value="url"><?php esc_html_e( 'Image URL', 'device-menu-manager' ); ?></option>
							</select>
						</div>

						<div class="dmm-form-group dmm-insp-visual-upload-row">
							<label class="dmm-label"><?php esc_html_e( 'Select Image from Media Library', 'device-menu-manager' ); ?></label>
							<div>
								<button type="button" class="dmm-btn dmm-btn-outline dmm-btn-select-media" data-prefix="insp-visual">
									<span class="dashicons dashicons-upload" style="vertical-align: middle; margin-right: 4px; font-size: 16px; width: 16px; height: 16px;"></span><?php esc_html_e( 'Upload / Select Image', 'device-menu-manager' ); ?>
								</button>
							</div>
						</div>

						<div class="dmm-form-group dmm-insp-visual-url-row" style="display: none;">
							<label class="dmm-label"><?php esc_html_e( 'Direct Image URL', 'device-menu-manager' ); ?></label>
							<input type="text" id="dmm-insp-visual-image-url-input" class="dmm-input" placeholder="https://handdn.us/wp-content/uploads/...">
						</div>

						<input type="hidden" id="dmm-insp-visual-image-url">

						<div class="dmm-form-group dmm-insp-visual-preview-wrap" style="display: none;">
							<label class="dmm-label"><?php esc_html_e( 'Image Preview', 'device-menu-manager' ); ?></label>
							<div class="dmm-media-preview dmm-insp-visual-preview">
								<img src="" alt="Preview">
								<button type="button" class="dmm-btn dmm-btn-ghost dmm-btn-remove-media" data-prefix="insp-visual"><?php esc_html_e( 'Remove', 'device-menu-manager' ); ?></button>
							</div>
						</div>

						<div class="dmm-form-group">
							<label class="dmm-label"><?php esc_html_e( 'Kicker (Eyebrow Title)', 'device-menu-manager' ); ?></label>
							<input type="text" id="dmm-insp-visual-kicker" class="dmm-input" placeholder="e.g. Exotic leathers, Regular leathers...">
						</div>

						<div class="dmm-form-group">
							<label class="dmm-label"><?php esc_html_e( 'Headline / Description', 'device-menu-manager' ); ?></label>
							<textarea id="dmm-insp-visual-headline" class="dmm-input dmm-textarea" rows="2" placeholder="e.g. 440 straps in alligator, ostrich, stingray and more."></textarea>
						</div>

						<div class="dmm-grid-2">
							<div class="dmm-form-group">
								<label class="dmm-label"><?php esc_html_e( 'CTA Text', 'device-menu-manager' ); ?></label>
								<input type="text" id="dmm-insp-visual-cta-text" class="dmm-input" placeholder="e.g. Shop exotic leathers">
							</div>
							<div class="dmm-form-group">
								<label class="dmm-label"><?php esc_html_e( 'CTA Link URL', 'device-menu-manager' ); ?></label>
								<input type="text" id="dmm-insp-visual-cta-url" class="dmm-input" placeholder="https://handdn.us/shop/...">
							</div>
						</div>
					</div>
				</div>

				<!-- Tab 3: Feature Card -->
				<div class="dmm-sheet-panel" id="dmm-insp-panel-feature">
					<div class="dmm-callout">
						<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
						<span><?php esc_html_e( 'Displays a highlight card at the bottom of the drill-down sub-panel for this item.', 'device-menu-manager' ); ?></span>
					</div>

					<div class="dmm-form-group">
						<div style="display: flex; justify-content: space-between; align-items: center; padding: 10px 14px; background: var(--dmm-muted-bg); border-radius: var(--dmm-radius);">
							<div>
								<span style="font-size: 13px; font-weight: 600; color: var(--dmm-text);"><?php esc_html_e( 'Enable Bottom Feature Card', 'device-menu-manager' ); ?></span>
								<p style="margin: 2px 0 0; font-size: 12px; color: var(--dmm-muted-text);"><?php esc_html_e( 'Feature highlight card inside the panel scroll area', 'device-menu-manager' ); ?></p>
							</div>
							<label class="dmm-switch">
								<input type="checkbox" id="dmm-insp-feature-enabled">
								<span class="dmm-switch-slider"></span>
							</label>
						</div>
					</div>

					<div id="dmm-insp-feature-fields" style="display: none;">
						<div class="dmm-form-group">
							<label class="dmm-label"><?php esc_html_e( 'Media Type', 'device-menu-manager' ); ?></label>
							<select id="dmm-insp-feature-media-type" class="dmm-input-select">
								<option value="upload"><?php esc_html_e( 'Upload Image', 'device-menu-manager' ); ?></option>
								<option value="url"><?php esc_html_e( 'Image URL', 'device-menu-manager' ); ?></option>
							</select>
						</div>

						<div class="dmm-form-group dmm-insp-feature-upload-row">
							<label class="dmm-label"><?php esc_html_e( 'Select Image from Media Library', 'device-menu-manager' ); ?></label>
							<div>
								<button type="button" class="dmm-btn dmm-btn-outline dmm-btn-select-media" data-prefix="insp-feature">
									<span class="dashicons dashicons-upload" style="vertical-align: middle; margin-right: 4px; font-size: 16px; width: 16px; height: 16px;"></span><?php esc_html_e( 'Upload / Select Image', 'device-menu-manager' ); ?>
								</button>
							</div>
						</div>

						<div class="dmm-form-group dmm-insp-feature-url-row" style="display: none;">
							<label class="dmm-label"><?php esc_html_e( 'Direct Image URL', 'device-menu-manager' ); ?></label>
							<input type="text" id="dmm-insp-feature-image-url-input" class="dmm-input" placeholder="https://handdn.us/wp-content/uploads/...">
						</div>

						<input type="hidden" id="dmm-insp-feature-image-url">

						<div class="dmm-form-group dmm-insp-feature-preview-wrap" style="display: none;">
							<label class="dmm-label"><?php esc_html_e( 'Image Preview', 'device-menu-manager' ); ?></label>
							<div class="dmm-media-preview dmm-insp-feature-preview">
								<img src="" alt="Preview">
								<button type="button" class="dmm-btn dmm-btn-ghost dmm-btn-remove-media" data-prefix="insp-feature"><?php esc_html_e( 'Remove', 'device-menu-manager' ); ?></button>
							</div>
						</div>

						<div class="dmm-form-group">
							<label class="dmm-label"><?php esc_html_e( 'Kicker (Subtitle)', 'device-menu-manager' ); ?></label>
							<input type="text" id="dmm-insp-feature-kicker" class="dmm-input" placeholder="e.g. Leather collection, Craft...">
						</div>

						<div class="dmm-form-group">
							<label class="dmm-label"><?php esc_html_e( 'Text Content', 'device-menu-manager' ); ?></label>
							<textarea id="dmm-insp-feature-text" class="dmm-input dmm-textarea" rows="2" placeholder="e.g. Every leather we cut, from Epsom to shell cordovan."></textarea>
						</div>

						<div class="dmm-grid-2">
							<div class="dmm-form-group">
								<label class="dmm-label"><?php esc_html_e( 'CTA Text', 'device-menu-manager' ); ?></label>
								<input type="text" id="dmm-insp-feature-cta-text" class="dmm-input" placeholder="e.g. Browse leathers">
							</div>
							<div class="dmm-form-group">
								<label class="dmm-label"><?php esc_html_e( 'CTA Link URL', 'device-menu-manager' ); ?></label>
								<input type="text" id="dmm-insp-feature-cta-url" class="dmm-input" placeholder="https://handdn.us/shop/...">
							</div>
						</div>
					</div>
				</div>
			</div>

			<!-- Sheet Footer -->
			<div class="dmm-sheet-footer">
				<button type="button" class="dmm-btn dmm-btn-primary dmm-btn-apply-inspector">
					<?php esc_html_e( 'Apply Changes', 'device-menu-manager' ); ?>
				</button>
				<button type="button" class="dmm-btn dmm-btn-ghost dmm-btn-close-inspector">
					<?php esc_html_e( 'Cancel', 'device-menu-manager' ); ?>
				</button>
			</div>
		</div>
	</div>

	<!-- Minimalist Delete Confirmation Modal -->
	<div id="dmm-delete-modal" class="dmm-modal-overlay" style="display: none;">
		<div class="dmm-dialog">
			<div class="dmm-dialog-header">
				<h3 class="dmm-dialog-title"><?php esc_html_e( 'Remove Item', 'device-menu-manager' ); ?></h3>
				<button type="button" class="dmm-dialog-close" id="dmm-modal-cancel-btn">&times;</button>
			</div>
			<div class="dmm-dialog-body">
				<p><?php esc_html_e( 'Are you sure you want to remove item:', 'device-menu-manager' ); ?> <strong id="dmm-modal-target-title"></strong>?</p>
				<p style="color: var(--dmm-muted-text); font-size: 13px;"><?php esc_html_e( 'Sub-items will be moved up one level so they are not orphaned.', 'device-menu-manager' ); ?></p>
			</div>
			<div class="dmm-dialog-footer">
				<button type="button" class="dmm-btn dmm-btn-danger" id="dmm-modal-confirm-btn"><?php esc_html_e( 'Delete', 'device-menu-manager' ); ?></button>
				<button type="button" class="dmm-btn dmm-btn-ghost" id="dmm-modal-abort-btn"><?php esc_html_e( 'Cancel', 'device-menu-manager' ); ?></button>
			</div>
		</div>
	</div>

</div>
