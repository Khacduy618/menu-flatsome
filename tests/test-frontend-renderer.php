<?php
/**
 * Test Frontend Drawer Renderer
 *
 * Verifies the HTML output of DMM_Frontend_Renderer.
 *
 * @package Device_Menu_Manager
 */

// Mock WordPress functions if not defined.
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $url ) {
		return filter_var( $url, FILTER_SANITIZE_URL );
	}
}

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $name, $default = false ) {
		global $mock_options;
		return $mock_options[ $name ] ?? $default;
	}
}

if ( ! function_exists( 'get_theme_mod' ) ) {
	function get_theme_mod( $name, $default = false ) {
		return 'https://handdn.ddev.site/wp-content/uploads/2026/11/handdn-logo.png';
	}
}

if ( ! function_exists( 'get_bloginfo' ) ) {
	function get_bloginfo( $show = 'name' ) {
		return 'Handdn';
	}
}

if ( ! function_exists( 'wp_parse_args' ) ) {
	function wp_parse_args( $args, $defaults = array() ) {
		if ( is_object( $args ) ) {
			$args = get_object_vars( $args );
		} elseif ( ! is_array( $args ) ) {
			$args = array();
		}
		return array_merge( $defaults, $args );
	}
}

if ( ! function_exists( 'home_url' ) ) {
	function home_url( $path = '' ) {
		return 'https://handdn.ddev.site' . $path;
	}
}

if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( $val ) {
		return is_string( $val ) ? stripslashes( $val ) : $val;
	}
}

if ( ! function_exists( 'absint' ) ) {
	function absint( $maybeint ) {
		return abs( intval( $maybeint ) );
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $str ) {
		return trim( strip_tags( (string) $str ) );
	}
}

if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( $url ) {
		return filter_var( (string) $url, FILTER_SANITIZE_URL ) ?: '';
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) {
		return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
	}
}

if ( ! function_exists( 'sanitize_hex_color' ) ) {
	function sanitize_hex_color( $color ) {
		return preg_match( '/^#([a-fA-F0-9]{3}){1,2}$/', $color ) ? $color : '';
	}
}

if ( ! function_exists( 'sanitize_html_class' ) ) {
	function sanitize_html_class( $class ) {
		return preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $class );
	}
}

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) {
		return $text;
	}
}

if ( ! function_exists( '_e' ) ) {
	function _e( $text, $domain = 'default' ) {
		echo $text;
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( $text, $domain = 'default' ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_attr__' ) ) {
	function esc_attr__( $text, $domain = 'default' ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_attr_e' ) ) {
	function esc_attr_e( $text, $domain = 'default' ) {
		echo htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'number_format_i18n' ) ) {
	function number_format_i18n( $number, $decimals = 0 ) {
		return number_format( (float) $number, $decimals );
	}
}

if ( ! function_exists( 'is_user_logged_in' ) ) {
	function is_user_logged_in() {
		global $mock_user_logged_in;
		return ! empty( $mock_user_logged_in );
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $tag, $value ) {
		return $value;
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $data, $options = 0, $depth = 512 ) {
		return json_encode( $data, $options, $depth );
	}
}

require_once __DIR__ . '/../includes/class-menu-data-manager.php';
require_once __DIR__ . '/../includes/class-menu-switcher.php';
require_once __DIR__ . '/../includes/class-frontend-renderer.php';

echo "Running DMM_Frontend_Renderer tests...\n";

// Populate sample data in mock options.
$sample_data = DMM_Menu_Data_Manager::get_sample_data();
global $mock_options;
$mock_options = array(
	'dmm_menu_data' => $sample_data,
	'dmm_settings'  => array(
		'enable_plugin'    => 1,
		'desktop_override' => 1,
		'desktop_source'   => 'custom_json',
	),
	'dmm_drawer_settings' => array(
		'promo_kicker'       => '3D Custom',
		'promo_headline'     => 'Design your strap in 3D, then we cut and stitch it by hand.',
		'promo_cta_text'     => 'Start designing',
		'promo_cta_url'      => 'https://handdn.us/product/3d-custom-watch-strap/',
		'promo_texture'      => 'grain',
		'promo_tone'         => '#8a4a26',
		'promo_show_strap'   => 1,
		'trustpilot_enabled' => 1,
		'trustpilot_auto'    => 0,
		'trustpilot_rating'  => '4.7',
		'trustpilot_count'   => 619,
		'trustpilot_url'     => 'https://www.trustpilot.com/review/handdn.com',
		'wc_myaccount_url'   => 'https://handdn.us/my-account/',
		'wc_track_url'       => 'https://handdn.us/my-account/track-order/',
		'wc_contact_url'     => 'https://handdn.us/contact/',
		'wc_instagram_url'   => 'https://www.instagram.com/handdn.store/',
	),
);

// Capture HTML output.
ob_start();
DMM_Frontend_Renderer::render();
$html = ob_get_clean();

// Assertions.
assert( ! empty( $html ), 'Output should not be empty' );
assert( strpos( $html, '<div class="scrim" id="scrim"' ) !== false, 'Must contain scrim' );
assert( strpos( $html, '<nav class="hm" id="hm"' ) !== false, 'Must contain main drawer #hm' );
assert( strpos( $html, 'id="p-root"' ) !== false, 'Must contain root panel #p-root' );
assert( strpos( $html, 'class="hm-list primary"' ) !== false, 'Must contain primary list' );
assert( strpos( $html, 'class="hm-list secondary"' ) !== false, 'Must contain secondary list' );
assert( strpos( $html, 'Watch Straps' ) !== false, 'Must render Watch Straps' );
assert( strpos( $html, 'Discover Handdn' ) !== false, 'Must render Discover Handdn' );
assert( strpos( $html, 'class="hm-promo' ) !== false, 'Must contain promo banner' );
assert( strpos( $html, 'Design your strap in 3D' ) !== false, 'Must contain promo headline' );
assert( strpos( $html, 'class="tp"' ) !== false, 'Must contain Trustpilot line' );
assert( strpos( $html, '619 reviews on Trustpilot' ) !== false, 'Must contain Trustpilot reviews count' );
assert( strpos( $html, 'class="sizes"' ) !== false, 'Must contain sizes grid' );
assert( strpos( $html, '26<small>mm</small>' ) !== false, 'Must contain 26mm in sizes grid' );
assert( strpos( $html, 'class="feature' ) !== false, 'Must contain feature card' );
assert( strpos( $html, 'id="hmVis"' ) !== false, 'Must contain #hmVis visual pane' );
assert( strpos( $html, 'id="srch"' ) !== false, 'Must contain #srch search modal' );

assert( strpos( $html, 'Sign in or register' ) !== false, 'Must show Sign in or register when guest' );

// Test logged-in state.
global $mock_user_logged_in;
$mock_user_logged_in = true;
ob_start();
DMM_Frontend_Renderer::render();
$html_logged_in = ob_get_clean();
assert( strpos( $html_logged_in, 'My Account' ) !== false, 'Must show My Account when logged in' );
assert( strpos( $html_logged_in, 'Sign in or register' ) === false, 'Must not show Sign in or register when logged in' );
$mock_user_logged_in = false;

// Test disabled promo banner.
$mock_options['dmm_settings']['root_promo'] = array( 'enabled' => 0 );
$mock_options['dmm_drawer_settings']['promo_enabled'] = 0;
ob_start();
DMM_Frontend_Renderer::render();
$html_no_promo = ob_get_clean();
assert( strpos( $html_no_promo, 'class="hm-promo' ) === false, 'Must NOT contain promo banner when promo is disabled' );

// Test disabled footer links and trustpilot.
$mock_options['dmm_settings']['drawer_footer'] = array(
	'trustpilot_enabled'   => 0,
	'account_link_enabled' => 0,
	'track_order_enabled'  => 0,
	'contact_link_enabled' => 0,
	'instagram_url'        => '',
);
$mock_options['dmm_drawer_settings']['trustpilot_enabled']   = 0;
$mock_options['dmm_drawer_settings']['account_link_enabled'] = 0;
$mock_options['dmm_drawer_settings']['track_order_enabled']  = 0;
$mock_options['dmm_drawer_settings']['contact_link_enabled'] = 0;
$mock_options['dmm_drawer_settings']['wc_instagram_url']     = '';

ob_start();
DMM_Frontend_Renderer::render();
$html_no_footer = ob_get_clean();
assert( strpos( $html_no_footer, 'class="tp"' ) === false, 'Must NOT contain Trustpilot when disabled' );
assert( strpos( $html_no_footer, 'Track order' ) === false, 'Must NOT contain Track order when disabled' );
assert( strpos( $html_no_footer, 'Sign in or register' ) === false, 'Must NOT contain Sign in when disabled' );
assert( strpos( $html_no_footer, 'class="hm-foot"' ) === false, 'Must NOT contain hm-foot when all footer items are disabled' );

// Test Level 0 Group Heading rendering.
$mock_options['dmm_menu_data'][] = array(
	'db_id'            => 99991,
	'title'            => 'TEST HEADING L0',
	'url'              => '#',
	'menu_item_parent' => '0',
	'section'          => 'secondary',
	'item_type'        => 'heading',
);
$mock_options['dmm_menu_data'][] = array(
	'db_id'            => 99992,
	'title'            => 'L0 Heading Child Item',
	'url'              => 'https://handdn.ddev.site/child-item',
	'menu_item_parent' => '99991',
	'section'          => 'secondary',
	'item_type'        => 'link',
);

ob_start();
DMM_Frontend_Renderer::render();
$html_heading = ob_get_clean();
assert( strpos( $html_heading, 'TEST HEADING L0' ) !== false, 'Must render Level 0 Group Heading title' );
assert( strpos( $html_heading, 'hm-heading-row' ) !== false, 'Must contain hm-heading-row class' );
assert( strpos( $html_heading, 'L0 Heading Child Item' ) !== false, 'Must render child items under Level 0 Group Heading' );

echo "All DMM_Frontend_Renderer tests passed successfully!\n";
