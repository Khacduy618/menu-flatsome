<?php
/**
 * Class DMM_Flatsome_Integrator
 *
 * Dedicated integration bridge for Flatsome Header Builder and Handdn luxury navigation drawer.
 *
 * @package Device_Menu_Manager
 */

defined( 'ABSPATH' ) || exit;

class DMM_Flatsome_Integrator {

	/**
	 * Init Flatsome hooks.
	 */
	public static function init(): void {
		add_filter( 'body_class', array( __CLASS__, 'add_body_classes' ) );
		add_action( 'wp_head', array( __CLASS__, 'inject_breakpoint_styles' ), 100 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_frontend_assets' ) );
		add_action( 'wp', array( __CLASS__, 'disable_flatsome_default_drawer' ) );
		add_action( 'wp_footer', array( __CLASS__, 'render_drawer' ), 99 );

		// Register Menu Drawer Trigger Shortcode.
		add_shortcode( 'dmm_menu_trigger', array( __CLASS__, 'render_menu_trigger_shortcode' ) );
	}

	/**
	 * Disable Flatsome default off-canvas mobile drawer so Handdn drawer takes over cleanly.
	 */
	public static function disable_flatsome_default_drawer(): void {
		$settings = DMM_Menu_Switcher::get_settings();
		if ( ! empty( $settings['enable_plugin'] ) ) {
			remove_action( 'wp_footer', 'flatsome_mobile_menu', 7 );
		}
	}

	/**
	 * Enqueue frontend styling and interactive scripts.
	 */
	public static function enqueue_frontend_assets(): void {
		$settings = DMM_Menu_Switcher::get_settings();
		if ( empty( $settings['enable_plugin'] ) ) {
			return;
		}

		// Enqueue Google Font Jost.
		wp_enqueue_style(
			'dmm-google-fonts',
			'https://fonts.googleapis.com/css2?family=Jost:wght@300;400;500;600&display=swap',
			array(),
			null
		);

		$css_file = DMM_PATH . 'assets/css/handdn-menu.css';
		$js_file  = DMM_PATH . 'assets/js/handdn-menu.js';
		$css_ver  = file_exists( $css_file ) ? (string) filemtime( $css_file ) : DMM_VERSION;
		$js_ver   = file_exists( $js_file ) ? (string) filemtime( $js_file ) : DMM_VERSION;

		// Enqueue Handdn Menu & Header Styles.
		wp_enqueue_style(
			'dmm-handdn-menu',
			DMM_URL . 'assets/css/handdn-menu.css',
			array(),
			$css_ver
		);

		// Enqueue Handdn Menu & Header Script.
		wp_enqueue_script(
			'dmm-handdn-menu',
			DMM_URL . 'assets/js/handdn-menu.js',
			array(),
			$js_ver,
			true
		);

		wp_localize_script(
			'dmm-handdn-menu',
			'DMM_FRONTEND',
			array(
				'home_url' => home_url( '/' ),
				'ajax_url' => admin_url( 'admin-ajax.php' ),
			)
		);
	}

	/**
	 * Render the full Handdn Drawer Menu HTML in wp_footer.
	 */
	public static function render_drawer(): void {
		$settings = DMM_Menu_Switcher::get_settings();
		if ( empty( $settings['enable_plugin'] ) ) {
			return;
		}

		DMM_Frontend_Renderer::render();
	}

	/**
	 * Add body classes when overrides are active.
	 *
	 * @param array $classes Body classes.
	 * @return array
	 */
	public static function add_body_classes( array $classes ): array {
		$settings = DMM_Menu_Switcher::get_settings();

		if ( empty( $settings['enable_plugin'] ) ) {
			return $classes;
		}

		$classes[] = 'dmm-active';

		if ( ! empty( $settings['desktop_override'] ) ) {
			$classes[] = 'dmm-desktop-override';
		}

		if ( ! empty( $settings['mobile_override'] ) ) {
			$classes[] = 'dmm-mobile-override';
		}

		return $classes;
	}

	/**
	 * Inject responsive breakpoint CSS variables.
	 */
	public static function inject_breakpoint_styles(): void {
		$settings = DMM_Menu_Switcher::get_settings();

		if ( empty( $settings['enable_plugin'] ) ) {
			return;
		}

		$breakpoint = absint( $settings['mobile_breakpoint'] ?? 849 );
		echo '<style id="dmm-device-vars">:root { --dmm-breakpoint: ' . esc_attr( $breakpoint ) . 'px; }</style>' . "\n";
	}

	/**
	 * Render Menu Drawer Trigger button shortcode [dmm_menu_trigger].
	 *
	 * Allows inserting a hamburger trigger button anywhere in Flatsome Header Builder,
	 * widgets, or page templates.
	 *
	 * Attributes:
	 *   - label: Button label text (default: 'Menu', empty for icon only).
	 *   - class: Additional CSS classes.
	 *   - icon: Show hamburger 3-line icon (default: 'true').
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string HTML button markup.
	 */
	public static function render_menu_trigger_shortcode( $atts = array() ): string {
		$atts = shortcode_atts(
			array(
				'label' => __( 'Menu', 'device-menu-manager' ),
				'class' => '',
				'icon'  => 'true',
			),
			$atts,
			'dmm_menu_trigger'
		);

		$icon_html = ( 'true' === (string) $atts['icon'] || '1' === (string) $atts['icon'] )
			? '<span class="dmm-trigger-icon" aria-hidden="true" style="display:inline-flex;flex-direction:column;gap:3px;margin-right:7px;vertical-align:middle;"><span style="width:16px;height:1.5px;background:currentColor;display:block;"></span><span style="width:16px;height:1.5px;background:currentColor;display:block;"></span><span style="width:16px;height:1.5px;background:currentColor;display:block;"></span></span>'
			: '';

		$label_html = ! empty( $atts['label'] ) ? '<span class="dmm-trigger-label">' . esc_html( $atts['label'] ) . '</span>' : '';

		return sprintf(
			'<button type="button" class="dmm-menu-trigger %1$s" aria-label="%2$s" style="background:transparent;border:none;cursor:pointer;display:inline-flex;align-items:center;padding:6px 10px;font-family:inherit;font-size:inherit;color:inherit;">%3$s%4$s</button>',
			esc_attr( $atts['class'] ),
			esc_attr( ! empty( $atts['label'] ) ? $atts['label'] : __( 'Open Navigation Menu', 'device-menu-manager' ) ),
			$icon_html,
			$label_html
		);
	}
}
