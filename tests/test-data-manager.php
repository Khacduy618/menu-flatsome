<?php
/**
 * Test script for DMM_Menu_Data_Manager extended schema.
 */

// Define WordPress mock functions if not running inside WordPress.
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', true );

	$GLOBALS['_mock_options'] = array();

	function get_option( $name, $default = false ) {
		return array_key_exists( $name, $GLOBALS['_mock_options'] ) ? $GLOBALS['_mock_options'][ $name ] : $default;
	}

	function update_option( $name, $value ) {
		$GLOBALS['_mock_options'][ $name ] = $value;
		return true;
	}

	function absint( $val ) {
		return intval( abs( $val ) );
	}

	function wp_parse_args( $args, $defaults = array() ) {
		if ( is_object( $args ) ) {
			$args = get_object_vars( $args );
		} elseif ( ! is_array( $args ) ) {
			$args = array();
		}
		return array_merge( $defaults, $args );
	}

	function sanitize_text_field( $str ) {
		return trim( strip_tags( (string) $str ) );
	}

	function esc_url_raw( $url ) {
		return filter_var( (string) $url, FILTER_SANITIZE_URL ) ?: '';
	}

	function sanitize_key( $key ) {
		return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
	}

	function sanitize_hex_color( $color ) {
		return preg_match( '/^#([a-fA-F0-9]{3}){1,2}$/', $color ) ? $color : '';
	}

	function sanitize_html_class( $class ) {
		return preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $class );
	}

	function wp_unslash( $val ) {
		return is_string( $val ) ? stripslashes( $val ) : $val;
	}

	function wp_rand( $min = 0, $max = 0 ) {
		return rand( $min, $max );
	}
}

require_once __DIR__ . '/../includes/class-menu-data-manager.php';

echo "Running DMM_Menu_Data_Manager Tests...\n";

// Test 1: Verify sanitize_menu_items preserves section, item_type, tag, visual, and feature
$raw_items = array(
	array(
		'db_id'            => 101,
		'title'            => 'Watch Straps',
		'url'              => 'https://example.com/shop',
		'menu_item_parent' => '0',
		'section'          => 'primary',
		'item_type'        => 'link',
		'tag'              => 'Must try',
		'classes'          => array( 'custom-class' ),
		'visual'           => array(
			'enabled'    => true,
			'media_type' => 'texture',
			'tex'        => 'grain',
			'tone'       => '#6b3b20',
			'kicker'     => 'Watch Straps',
			'headline'   => 'Hand-cut straps.',
			'cta_text'   => 'Shop all',
			'cta_url'    => 'https://example.com/shop',
		),
		'feature'          => array(
			'enabled'    => true,
			'media_type' => 'texture',
			'tex'        => 'smooth',
			'tone'       => '#3b2416',
			'kicker'     => 'Craft',
			'text'       => 'Hand-stitched.',
			'cta_text'   => 'Read more',
			'cta_url'    => 'https://example.com/craft',
		),
	),
	array(
		'db_id'            => 102,
		'title'            => 'BY MATERIAL',
		'url'              => 'https://should-be-cleared.com',
		'menu_item_parent' => '101',
		'item_type'        => 'heading',
	),
);

$sanitized = DMM_Menu_Data_Manager::sanitize_menu_items( $raw_items );

// Assertions for item 1
assert( count( $sanitized ) === 2, 'Must have 2 items' );
assert( $sanitized[0]['section'] === 'primary', 'Section must be primary' );
assert( $sanitized[0]['item_type'] === 'link', 'Item type must be link' );
assert( $sanitized[0]['tag'] === 'Must try', 'Tag must be "Must try"' );
assert( $sanitized[0]['visual']['enabled'] === true, 'Visual must be enabled' );
assert( $sanitized[0]['visual']['tex'] === 'grain', 'Visual tex must be grain' );
assert( $sanitized[0]['visual']['tone'] === '#6b3b20', 'Visual tone must be #6b3b20' );
assert( $sanitized[0]['feature']['enabled'] === true, 'Feature must be enabled' );

// Assertion for item 2 (heading URL forced to #)
assert( $sanitized[1]['item_type'] === 'heading', 'Item 2 must be heading' );
assert( $sanitized[1]['url'] === '#', 'Heading URL must be sanitized to #' );

// Test 2: Migration from dmm_desktop_menu_data to dmm_menu_data
$GLOBALS['_mock_options'] = array();
$GLOBALS['_mock_options']['dmm_desktop_menu_data'] = array(
	array(
		'db_id'            => 999,
		'title'            => 'Old Desktop Item',
		'url'              => 'https://example.com/old',
		'menu_item_parent' => '0',
	),
);

$retrieved = DMM_Menu_Data_Manager::get_menu_items();
assert( ! empty( $retrieved ), 'Must retrieve legacy items' );
assert( $retrieved[0]['title'] === 'Old Desktop Item', 'Must retrieve legacy title' );
assert( $retrieved[0]['section'] === 'primary', 'Legacy item must default to primary section' );

// Test 3: Trustpilot auto-detect fallback
$tp_data = DMM_Menu_Data_Manager::get_trustpilot_data();
assert( isset( $tp_data['rating'] ) && $tp_data['rating'] === '4.7', 'Trustpilot default rating must be 4.7' );
assert( isset( $tp_data['count'] ) && $tp_data['count'] === '619', 'Trustpilot default count must be 619' );

echo "ALL TESTS PASSED!\n";
