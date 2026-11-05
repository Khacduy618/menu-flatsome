<?php
/**
 * Class DMM_Admin_Settings
 *
 * Handles the WP Admin settings screen, tabs, and AJAX handlers.
 *
 * @package Device_Menu_Manager
 */

defined( 'ABSPATH' ) || exit;

class DMM_Admin_Settings {

	/**
	 * Init admin hooks.
	 */
	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'register_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );

		// AJAX Endpoints.
		add_action( 'wp_ajax_dmm_save_all_settings', array( __CLASS__, 'ajax_save_all_settings' ) );
		add_action( 'wp_ajax_dmm_import_wp_menu', array( __CLASS__, 'ajax_import_wp_menu' ) );
		add_action( 'wp_ajax_dmm_reset_sample_data', array( __CLASS__, 'ajax_reset_sample_data' ) );
	}

	/**
	 * Register Device Menus admin page in both Top-Level sidebar and Appearance submenu.
	 */
	public static function register_admin_menu(): void {
		// Top-level menu in admin sidebar.
		add_menu_page(
			__( 'Device Menus', 'device-menu-manager' ),
			__( 'Device Menus', 'device-menu-manager' ),
			'manage_options',
			'device-menu-manager',
			array( __CLASS__, 'render_admin_page' ),
			'dashicons-menu-alt3',
			60
		);

		// Also register under Appearance > Device Menus.
		add_theme_page(
			__( 'Device Menus', 'device-menu-manager' ),
			__( 'Device Menus', 'device-menu-manager' ),
			'manage_options',
			'device-menu-manager',
			array( __CLASS__, 'render_admin_page' )
		);
	}

	/**
	 * Enqueue styles and scripts for the admin builder page.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 */
	public static function enqueue_assets( string $hook_suffix ): void {
		if ( false === strpos( $hook_suffix, 'device-menu-manager' ) ) {
			return;
		}

		// WordPress Media Uploader for image selections.
		wp_enqueue_media();

		// WordPress Color Picker for tone selections.
		wp_enqueue_style( 'wp-color-picker' );

		$css_file = DMM_PATH . 'assets/css/admin-builder.css';
		$js_file  = DMM_PATH . 'assets/js/admin-builder.js';
		$css_ver  = file_exists( $css_file ) ? (string) filemtime( $css_file ) : DMM_VERSION;
		$js_ver   = file_exists( $js_file ) ? (string) filemtime( $js_file ) : DMM_VERSION;

		wp_enqueue_style(
			'dmm-admin-builder',
			DMM_URL . 'assets/css/admin-builder.css',
			array( 'wp-color-picker' ),
			$css_ver
		);

		// Use WordPress core jQuery UI Sortable for robust nested dragging.
		wp_enqueue_script( 'jquery-ui-sortable' );
		wp_enqueue_script( 'wp-color-picker' );

		wp_enqueue_script(
			'dmm-admin-builder',
			DMM_URL . 'assets/js/admin-builder.js',
			array( 'jquery', 'jquery-ui-sortable', 'wp-color-picker' ),
			$js_ver,
			true
		);

		// Localize script with initial data and nonces.
		$settings        = get_option( 'dmm_settings', array() );
		$menu_data       = DMM_Menu_Data_Manager::get_menu_items( 'desktop' );
		$trustpilot_data = DMM_Menu_Data_Manager::get_trustpilot_data();

		wp_localize_script(
			'dmm-admin-builder',
			'dmmAdmin',
			array(
				'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
				'nonce'          => wp_create_nonce( 'dmm_admin_nonce' ),
				'settings'       => $settings,
				'menuData'       => $menu_data,
				'desktopData'    => $menu_data,
				'mobileData'     => $menu_data,
				'trustpilotData' => $trustpilot_data,
				'strings'        => array(
					'savedSuccess'    => __( 'Settings and menu hierarchy saved successfully!', 'device-menu-manager' ),
					'saveError'       => __( 'Error saving data. Please try again.', 'device-menu-manager' ),
					'confirmDelete'   => __( 'Are you sure you want to delete this menu item and all its sub-items?', 'device-menu-manager' ),
					'confirmReset'    => __( 'Reset to default HandDN sample data? Existing changes will be replaced.', 'device-menu-manager' ),
					'importSuccess'   => __( 'Menu imported successfully into builder!', 'device-menu-manager' ),
					'importError'     => __( 'Failed to import WordPress menu. Please verify the menu selection.', 'device-menu-manager' ),
					'invalidJson'     => __( 'Invalid JSON format. Please verify your syntax.', 'device-menu-manager' ),
					'jsonApplied'     => __( 'JSON applied to tree builder successfully!', 'device-menu-manager' ),
					'selectImage'     => __( 'Select or Upload Banner Image', 'device-menu-manager' ),
					'useImage'        => __( 'Use This Image', 'device-menu-manager' ),
				),
			)
		);
	}

	/**
	 * Render the full settings and builder page.
	 */
	public static function render_admin_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized access.', 'device-menu-manager' ) );
		}

		$settings = wp_parse_args(
			get_option( 'dmm_settings', array() ),
			array(
				'enable_plugin'     => 1,
				'desktop_override'  => 1,
				'desktop_source'    => 'custom_json',
				'desktop_wp_menu'   => 0,
				'mobile_override'   => 1,
				'mobile_source'     => 'custom_json',
				'mobile_wp_menu'    => 0,
				'mobile_breakpoint' => 849,
			)
		);

		$wp_menus = wp_get_nav_menus();

		include DMM_PATH . 'templates/admin-menu-builder.php';
	}

	/**
	 * AJAX: Import core WP Menu.
	 */
	public static function ajax_import_wp_menu(): void {
		check_ajax_referer( 'dmm_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'device-menu-manager' ) ) );
		}

		$menu_id = isset( $_POST['menu_id'] ) ? absint( $_POST['menu_id'] ) : 0;
		if ( ! $menu_id ) {
			wp_send_json_error( array( 'message' => __( 'Please select a valid WordPress menu.', 'device-menu-manager' ) ) );
		}

		$converted = DMM_Menu_Data_Manager::import_from_wp_nav_menu( $menu_id );
		if ( empty( $converted ) ) {
			wp_send_json_error( array( 'message' => __( 'Selected menu has no items to import.', 'device-menu-manager' ) ) );
		}

		wp_send_json_success( array( 'items' => $converted ) );
	}

	/**
	 * AJAX: Save all settings (Menu data, General settings, Root promo, Drawer footer).
	 */
	public static function ajax_save_all_settings(): void {
		check_ajax_referer( 'dmm_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'device-menu-manager' ) ) );
		}

		// 1. Save Menu Data
		if ( isset( $_POST['menu_data'] ) ) {
			$raw_menu   = wp_unslash( $_POST['menu_data'] );
			$menu_items = json_decode( $raw_menu, true );
			if ( is_array( $menu_items ) ) {
				DMM_Menu_Data_Manager::save_menu_items( 'desktop', $menu_items );
			}
		}

		// 2. Save General Settings
		$existing_settings = get_option( 'dmm_settings', array() );
		$clean_settings    = is_array( $existing_settings ) ? $existing_settings : array();

		if ( isset( $_POST['general_settings'] ) ) {
			$raw_general = wp_unslash( $_POST['general_settings'] );
			$general     = json_decode( $raw_general, true );
			if ( is_array( $general ) ) {
				$clean_settings['enable_plugin']     = ! empty( $general['enable_plugin'] ) ? 1 : 0;
				$clean_settings['mobile_breakpoint'] = absint( $general['mobile_breakpoint'] ?? 767 );
				$clean_settings['desktop_override']  = isset( $general['desktop_override'] ) ? ( ! empty( $general['desktop_override'] ) ? 1 : 0 ) : 1;
				$clean_settings['mobile_override']   = isset( $general['mobile_override'] ) ? ( ! empty( $general['mobile_override'] ) ? 1 : 0 ) : 1;
				$clean_settings['desktop_source']    = sanitize_text_field( $general['desktop_source'] ?? 'custom_json' );
				$clean_settings['mobile_source']     = sanitize_text_field( $general['mobile_source'] ?? 'custom_json' );
				if ( isset( $general['banner_fallback'] ) || isset( $general['banner_fallback_opacity'] ) ) {
					$fallback                                  = DMM_Menu_Switcher::get_banner_fallback( array_merge( $clean_settings, $general ) );
					$clean_settings['banner_fallback']         = $fallback['mode'];
					$clean_settings['banner_fallback_opacity'] = $fallback['opacity'];
				}
			}
		}

		// 3. Save Drawer Settings (Promo Banner & Footer Links)
		$drawer_settings = get_option( 'dmm_drawer_settings', array() );
		if ( ! is_array( $drawer_settings ) ) {
			$drawer_settings = array();
		}

		if ( isset( $_POST['root_promo'] ) ) {
			$raw_promo = wp_unslash( $_POST['root_promo'] );
			$promo     = json_decode( $raw_promo, true );
			if ( is_array( $promo ) ) {
				$clean_settings['root_promo'] = array(
					'enabled'        => ! empty( $promo['enabled'] ),
					'media_type'     => ( isset( $promo['media_type'] ) && 'url' === $promo['media_type'] ) ? 'url' : 'upload',
					'image_url'      => isset( $promo['image_url'] ) ? esc_url_raw( $promo['image_url'] ) : '',
					'tex'            => isset( $promo['tex'] ) ? sanitize_key( $promo['tex'] ) : 'grain',
					'tone'           => isset( $promo['tone'] ) ? ( sanitize_hex_color( $promo['tone'] ) ?: '#8a4a26' ) : '#8a4a26',
					'kicker'         => isset( $promo['kicker'] ) ? sanitize_text_field( $promo['kicker'] ) : '',
					'headline'       => isset( $promo['headline'] ) ? sanitize_text_field( $promo['headline'] ) : '',
					'cta_text'       => isset( $promo['cta_text'] ) ? sanitize_text_field( $promo['cta_text'] ) : '',
					'cta_url'        => isset( $promo['cta_url'] ) ? esc_url_raw( $promo['cta_url'] ) : '',
					'show_strap_svg' => ! empty( $promo['show_strap_svg'] ),
				);

				$drawer_settings['promo_enabled']    = ! empty( $promo['enabled'] ) ? 1 : 0;
				$drawer_settings['promo_media_type'] = ( isset( $promo['media_type'] ) && 'url' === $promo['media_type'] ) ? 'url' : 'upload';
				$drawer_settings['promo_texture']    = sanitize_key( $promo['tex'] ?? 'grain' );
				$drawer_settings['promo_tone']       = sanitize_hex_color( $promo['tone'] ?? '#8a4a26' ) ?: '#8a4a26';
				$drawer_settings['promo_image_url']  = esc_url_raw( $promo['image_url'] ?? '' );
				$drawer_settings['promo_kicker']     = sanitize_text_field( $promo['kicker'] ?? '' );
				$drawer_settings['promo_headline']   = sanitize_text_field( $promo['headline'] ?? '' );
				$drawer_settings['promo_cta_text']   = sanitize_text_field( $promo['cta_text'] ?? '' );
				$drawer_settings['promo_cta_url']    = esc_url_raw( $promo['cta_url'] ?? '' );
				$drawer_settings['promo_show_strap'] = ! empty( $promo['show_strap_svg'] ) ? 1 : 0;
			}
		}

		if ( isset( $_POST['drawer_footer'] ) ) {
			$raw_footer = wp_unslash( $_POST['drawer_footer'] );
			$footer     = json_decode( $raw_footer, true );
			if ( is_array( $footer ) ) {
				$clean_settings['drawer_footer'] = array(
					'trustpilot_enabled'   => ! empty( $footer['trustpilot_enabled'] ),
					'trustpilot_auto'      => ! empty( $footer['trustpilot_auto'] ),
					'trustpilot_rating'    => isset( $footer['trustpilot_rating'] ) ? sanitize_text_field( $footer['trustpilot_rating'] ) : '4.7',
					'trustpilot_count'     => isset( $footer['trustpilot_count'] ) ? sanitize_text_field( $footer['trustpilot_count'] ) : '619',
					'trustpilot_url'       => isset( $footer['trustpilot_url'] ) ? esc_url_raw( $footer['trustpilot_url'] ) : 'https://www.trustpilot.com/review/handdn.com',
					'account_link_enabled' => ! empty( $footer['account_link_enabled'] ),
					'track_order_enabled'  => ! empty( $footer['track_order_enabled'] ),
					'contact_link_enabled' => ! empty( $footer['contact_link_enabled'] ),
					'instagram_url'        => isset( $footer['instagram_url'] ) ? esc_url_raw( $footer['instagram_url'] ) : 'https://www.instagram.com/handdn.store/',
				);

				$drawer_settings['trustpilot_enabled']   = ! empty( $footer['trustpilot_enabled'] ) ? 1 : 0;
				$drawer_settings['trustpilot_auto']      = ! empty( $footer['trustpilot_auto'] ) ? 1 : 0;
				$drawer_settings['trustpilot_rating']    = sanitize_text_field( $footer['trustpilot_rating'] ?? '4.7' );
				$drawer_settings['trustpilot_count']     = sanitize_text_field( $footer['trustpilot_count'] ?? '619' );
				$drawer_settings['trustpilot_url']       = esc_url_raw( $footer['trustpilot_url'] ?? 'https://www.trustpilot.com/review/handdn.com' );
				$drawer_settings['account_link_enabled'] = ! empty( $footer['account_link_enabled'] ) ? 1 : 0;
				$drawer_settings['track_order_enabled']  = ! empty( $footer['track_order_enabled'] ) ? 1 : 0;
				$drawer_settings['contact_link_enabled'] = ! empty( $footer['contact_link_enabled'] ) ? 1 : 0;
				if ( ! empty( $footer['instagram_url'] ) ) {
					$drawer_settings['wc_instagram_url'] = esc_url_raw( $footer['instagram_url'] );
				}
			}
		}

		update_option( 'dmm_settings', $clean_settings );
		update_option( 'dmm_drawer_settings', $drawer_settings );

		// Clear transients / caches so updates appear immediately.
		delete_transient( 'dmm_drawer_html_guest' );
		delete_transient( 'dmm_drawer_html_logged_in' );

		if ( function_exists( 'rocket_clean_domain' ) ) {
			rocket_clean_domain();
		}
		if ( function_exists( 'wp_cache_flush' ) ) {
			wp_cache_flush();
		}

		wp_send_json_success( array( 'message' => __( 'All changes saved successfully!', 'device-menu-manager' ) ) );
	}

	/**
	 * AJAX: Reset to sample HandDN dataset.
	 */
	public static function ajax_reset_sample_data(): void {
		check_ajax_referer( 'dmm_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'device-menu-manager' ) ) );
		}

		$device = isset( $_POST['device'] ) ? sanitize_key( $_POST['device'] ) : 'desktop';
		$sample = DMM_Menu_Data_Manager::get_sample_data();

		DMM_Menu_Data_Manager::save_menu_items( $device, $sample );

		// Also reset promo and drawer settings to default presets.
		$settings = get_option( 'dmm_settings', array() );
		$settings['root_promo'] = array(
			'enabled'        => 1,
			'media_type'     => 'texture',
			'image_url'      => '',
			'tex'            => 'grain',
			'tone'           => '#8a4a26',
			'kicker'         => '3D Custom',
			'headline'       => 'Design your strap in 3D, then we cut and stitch it by hand.',
			'cta_text'       => 'Start designing',
			'cta_url'        => 'https://handdn.us/product/3d-custom-watch-strap/',
			'show_strap_svg' => 1,
		);
		$settings['drawer_footer'] = array(
			'trustpilot_enabled'   => 1,
			'trustpilot_auto'      => 0,
			'trustpilot_rating'    => '4.7',
			'trustpilot_count'     => '619',
			'trustpilot_url'       => 'https://www.trustpilot.com/review/handdn.com',
			'account_link_enabled' => 1,
			'track_order_enabled'  => 1,
			'contact_link_enabled' => 1,
			'instagram_url'        => 'https://www.instagram.com/handdn.store/',
		);
		update_option( 'dmm_settings', $settings );

		delete_transient( 'dmm_drawer_html_guest' );
		delete_transient( 'dmm_drawer_html_logged_in' );

		wp_send_json_success( array( 'items' => $sample ) );
	}
}
