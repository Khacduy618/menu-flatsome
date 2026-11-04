<?php
/**
 * Plugin Name: Device Menu Manager
 * Plugin URI: https://handdn.us/
 * Description: Configure independent menus and hierarchical menu data for Desktop and Mobile devices with drag-and-drop tree editing.
 * Version: 1.3.1
 * Author: HandDN Atelier
 * Author URI: https://handdn.us/
 * Text Domain: device-menu-manager
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 *
 * @package Device_Menu_Manager
 */

defined( 'ABSPATH' ) || exit;

// Define plugin constants.
define( 'DMM_VERSION', '1.3.1' );
define( 'DMM_FILE', __FILE__ );
define( 'DMM_PATH', plugin_dir_path( __FILE__ ) );
define( 'DMM_URL', plugin_dir_url( __FILE__ ) );

/**
 * Main Device Menu Manager Class.
 */
final class Device_Menu_Manager {

	/**
	 * Single instance of the class.
	 *
	 * @var Device_Menu_Manager|null
	 */
	private static $instance = null;

	/**
	 * Main instance accessor.
	 *
	 * @return Device_Menu_Manager
	 */
	public static function instance(): Device_Menu_Manager {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->includes();
		$this->init_hooks();
	}

	/**
	 * Include required files.
	 */
	private function includes(): void {
		require_once DMM_PATH . 'includes/class-menu-data-manager.php';
		require_once DMM_PATH . 'includes/class-admin-settings.php';
		require_once DMM_PATH . 'includes/class-menu-switcher.php';
		require_once DMM_PATH . 'includes/class-frontend-renderer.php';
		require_once DMM_PATH . 'includes/class-flatsome-integrator.php';
	}

	/**
	 * Initialize hooks.
	 */
	private function init_hooks(): void {
		register_activation_hook( DMM_FILE, array( $this, 'on_activation' ) );
		register_deactivation_hook( DMM_FILE, array( $this, 'on_deactivation' ) );

		add_action( 'plugins_loaded', array( $this, 'boot_components' ) );
	}

	/**
	 * Boot plugin components.
	 */
	public function boot_components(): void {
		// Initialize Data Manager.
		DMM_Menu_Data_Manager::init();

		// Initialize Admin Settings & AJAX handlers.
		if ( is_admin() ) {
			DMM_Admin_Settings::init();
		}

		// Initialize Front-end Switcher & Flatsome Integration.
		DMM_Menu_Switcher::init();
		DMM_Flatsome_Integrator::init();
	}

	/**
	 * Plugin activation callback.
	 */
	public function on_activation(): void {
		// Set default settings if not already present.
		$existing_settings = get_option( 'dmm_settings', array() );
		if ( empty( $existing_settings ) || ! is_array( $existing_settings ) ) {
			$existing_settings = array();
		}

		$default_settings = array(
			'enable_plugin'     => 1,
			'desktop_override'  => 1,
			'desktop_source'    => 'custom_json',
			'desktop_wp_menu'   => 0,
			'mobile_override'   => 1,
			'mobile_source'     => 'custom_json',
			'mobile_wp_menu'    => 0,
			'mobile_breakpoint' => 849,
			'root_promo'        => array(
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
			),
			'drawer_footer'     => array(
				'trustpilot_enabled'   => 1,
				'trustpilot_auto'      => 0,
				'trustpilot_rating'    => '4.7',
				'trustpilot_count'     => '619',
				'trustpilot_url'       => 'https://www.trustpilot.com/review/handdn.com',
				'account_link_enabled' => 1,
				'track_order_enabled'  => 1,
				'contact_link_enabled' => 1,
				'instagram_url'        => 'https://www.instagram.com/handdn.store/',
			),
		);

		update_option( 'dmm_settings', wp_parse_args( $existing_settings, $default_settings ) );

		// Seed initial sample data if empty.
		DMM_Menu_Data_Manager::seed_initial_data();
	}

	/**
	 * Plugin deactivation callback.
	 */
	public function on_deactivation(): void {
		// Flush rewrite rules or temporary transients if needed.
	}
}

/**
 * Return main plugin instance.
 *
 * @return Device_Menu_Manager
 */
function device_menu_manager(): Device_Menu_Manager {
	return Device_Menu_Manager::instance();
}

// Start the engine.
device_menu_manager();
