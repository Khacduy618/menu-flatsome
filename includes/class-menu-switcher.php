<?php
/**
 * Class DMM_Menu_Switcher
 *
 * Core engine intercepting WordPress navigation arguments and providing tree access.
 *
 * @package Device_Menu_Manager
 */

defined( 'ABSPATH' ) || exit;

class DMM_Menu_Switcher {

	/**
	 * Init switcher hooks.
	 */
	public static function init(): void {
		add_filter( 'wp_nav_menu_args', array( __CLASS__, 'filter_nav_menu_args' ), 99 );
		add_filter( 'theme_mod_nav_menu_locations', array( __CLASS__, 'filter_nav_menu_locations' ), 99 );
	}

	/**
	 * Get plugin settings.
	 *
	 * @return array
	 */
	public static function get_settings(): array {
		return wp_parse_args(
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
	}

	/**
	 * Filter wp_nav_menu arguments when standard WP menu source is chosen.
	 *
	 * @param array $args Arguments for wp_nav_menu.
	 * @return array
	 */
	public static function filter_nav_menu_args( $args ): array {
		$settings = self::get_settings();

		// Master switch check.
		if ( empty( $settings['enable_plugin'] ) ) {
			return $args;
		}

		$theme_location = $args['theme_location'] ?? '';
		$walker         = isset( $args['walker'] ) ? ( is_object( $args['walker'] ) ? get_class( $args['walker'] ) : (string) $args['walker'] ) : '';

		// Detect if this call represents Desktop Primary Nav or Mobile Sidebar Nav.
		$is_mobile_nav = ( 'primary_mobile' === $theme_location || 'FlatsomeNavSidebar' === $walker );
		$is_desktop_nav = ( 'primary' === $theme_location && ! $is_mobile_nav );

		// Desktop Override handling for existing WP Menu source.
		if ( $is_desktop_nav && ! empty( $settings['desktop_override'] ) ) {
			if ( 'wp_menu' === $settings['desktop_source'] && ! empty( $settings['desktop_wp_menu'] ) ) {
				$args['menu'] = absint( $settings['desktop_wp_menu'] );
			}
		}

		// Mobile Override handling for existing WP Menu source.
		if ( $is_mobile_nav && ! empty( $settings['mobile_override'] ) ) {
			if ( 'wp_menu' === $settings['mobile_source'] && ! empty( $settings['mobile_wp_menu'] ) ) {
				$args['menu'] = absint( $settings['mobile_wp_menu'] );
			}
		}

		return $args;
	}

	/**
	 * Ensure nav menu locations evaluate to true so themes render navigation containers.
	 *
	 * @param array $locations Menu locations array.
	 * @return array
	 */
	public static function filter_nav_menu_locations( $locations ): array {
		$settings = self::get_settings();

		if ( empty( $settings['enable_plugin'] ) ) {
			return $locations;
		}

		if ( ! is_array( $locations ) ) {
			$locations = array();
		}

		// Force location assignment if override is on and standard WP menu selected.
		if ( ! empty( $settings['desktop_override'] ) && 'wp_menu' === $settings['desktop_source'] && ! empty( $settings['desktop_wp_menu'] ) ) {
			$locations['primary'] = absint( $settings['desktop_wp_menu'] );
		}

		if ( ! empty( $settings['mobile_override'] ) && 'wp_menu' === $settings['mobile_source'] && ! empty( $settings['mobile_wp_menu'] ) ) {
			$locations['primary_mobile'] = absint( $settings['mobile_wp_menu'] );
		}

		return $locations;
	}

	/**
	 * Public API to fetch parsed hierarchical tree for any frontend renderer.
	 *
	 * @param string $device 'desktop' or 'mobile'.
	 * @return array Hierarchical tree with 'children' keys.
	 */
	public static function get_active_menu_tree( string $device = 'desktop' ): array {
		$settings = self::get_settings();

		if ( empty( $settings['enable_plugin'] ) ) {
			return array();
		}

		$source = ( 'mobile' === $device ) ? $settings['mobile_source'] : $settings['desktop_source'];

		if ( 'wp_menu' === $source ) {
			$menu_id = ( 'mobile' === $device ) ? $settings['mobile_wp_menu'] : $settings['desktop_wp_menu'];
			if ( empty( $menu_id ) ) {
				return array();
			}
			$items = DMM_Menu_Data_Manager::import_from_wp_nav_menu( (int) $menu_id );
		} else {
			$items = DMM_Menu_Data_Manager::get_menu_items( $device );
		}

		return DMM_Menu_Data_Manager::build_tree( $items, '0' );
	}
}
