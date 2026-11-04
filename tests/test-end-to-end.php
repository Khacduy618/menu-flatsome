<?php
/**
 * End-to-end integration test verifying complete Handdn Prototype architecture.
 */

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

echo "Running End-to-End Integration Tests...\n";

// 1. Verify get_sample_data returns non-empty structured array
$sample_data = DMM_Menu_Data_Manager::get_sample_data();
assert( ! empty( $sample_data ) && is_array( $sample_data ), 'Sample data must be a non-empty array' );
echo "  ✓ Sample data loaded with " . count( $sample_data ) . " items.\n";

// 2. Verify sanitization
$sanitized = DMM_Menu_Data_Manager::sanitize_menu_items( $sample_data );
assert( count( $sanitized ) === count( $sample_data ), 'Sanitized count must match sample count' );
echo "  ✓ All items sanitized cleanly.\n";

// 3. Verify Primary vs Secondary root division
$roots = array_filter( $sanitized, function( $item ) {
	return (string) $item['menu_item_parent'] === '0';
});
$primary_roots = array_filter( $roots, function( $item ) {
	return $item['section'] === 'primary';
});
$secondary_roots = array_filter( $roots, function( $item ) {
	return $item['section'] === 'secondary';
});

assert( count( $primary_roots ) >= 4, 'Must have at least 4 primary root items (Watch Straps, Bespoke, Apple Watch, Gifts...)' );
assert( count( $secondary_roots ) >= 2, 'Must have 2 secondary root items (Discover Handdn, Client Services)' );
echo "  ✓ Root items partitioned correctly: " . count( $primary_roots ) . " Primary, " . count( $secondary_roots ) . " Secondary.\n";

// 4. Verify Group Headings
$headings = array_filter( $sanitized, function( $item ) {
	return $item['item_type'] === 'heading';
});
assert( ! empty( $headings ), 'Must have group heading items (BY MATERIAL, BY FIT, etc.)' );
foreach ( $headings as $h ) {
	assert( $h['url'] === '#', 'Heading URL must be #' );
}
echo "  ✓ Found " . count( $headings ) . " Group Headings with valid '#' URLs.\n";

// 5. Verify Sizes Grid item
$sizes = array_filter( $sanitized, function( $item ) {
	return $item['item_type'] === 'sizes_grid';
});
assert( count( $sizes ) >= 1, 'Must have at least 1 sizes_grid item (Shop by lug width)' );
$first_sizes = reset( $sizes );
assert( ! empty( $first_sizes['visual']['enabled'] ), 'Sizes grid item must have visual banner enabled' );
echo "  ✓ Verified Sizes Grid item with active Visual Banner.\n";

// 6. Verify Visual Banners for parent and child
$watch_straps = array_filter( $sanitized, function( $item ) {
	return $item['title'] === 'Watch Straps';
});
$ws = reset( $watch_straps );
assert( $ws['visual']['enabled'] === true, 'Watch Straps must have visual enabled' );
assert( $ws['visual']['tex'] === 'grain', 'Watch Straps tex must be grain' );
assert( $ws['feature']['enabled'] === true, 'Watch Straps must have feature card enabled' );

$alligator = array_filter( $sanitized, function( $item ) {
	return $item['title'] === 'Alligator';
});
$alli = reset( $alligator );
assert( $alli['visual']['enabled'] === true, 'Alligator child must have custom visual enabled' );
assert( $alli['visual']['tex'] === 'scales', 'Alligator tex must be scales' );
echo "  ✓ Verified parent and child hover Visual Banners.\n";

// 7. Verify save_menu_items writes to unified dmm_menu_data option
DMM_Menu_Data_Manager::save_menu_items( 'desktop', $sanitized );
assert( ! empty( $GLOBALS['_mock_options']['dmm_menu_data'] ), 'dmm_menu_data option must be saved' );
$loaded_back = DMM_Menu_Data_Manager::get_menu_items();
assert( count( $loaded_back ) === count( $sanitized ), 'Loaded back items must match saved items' );
echo "  ✓ Verified save and retrieval from unified dmm_menu_data option.\n";

// 8. Verify Child Section Inheritance (IMP-1)
$tree = DMM_Menu_Data_Manager::build_tree( $sanitized );
$discover_handdn = null;
foreach ( $tree as $node ) {
	if ( $node['title'] === 'Discover Handdn' ) {
		$discover_handdn = $node;
		break;
	}
}
assert( $discover_handdn !== null, 'Discover Handdn must exist in tree' );
assert( $discover_handdn['section'] === 'secondary', 'Discover Handdn must be secondary' );
assert( ! empty( $discover_handdn['children'] ), 'Discover Handdn must have children' );
foreach ( $discover_handdn['children'] as $child ) {
	assert( $child['section'] === 'secondary', 'Child ' . $child['title'] . ' must inherit secondary section' );
}
echo "  ✓ Verified secondary child section inheritance.\n";

// 9. Verify Trustpilot enabled toggle (IMP-3)
$GLOBALS['_mock_options']['dmm_settings'] = array(
	'drawer_footer' => array(
		'trustpilot_enabled' => false,
	),
);
$tp_disabled = DMM_Menu_Data_Manager::get_trustpilot_data();
assert( $tp_disabled['enabled'] === false, 'Trustpilot enabled must be false when disabled in settings' );
echo "  ✓ Verified Trustpilot enabled toggle respected.\n";

// 10. Verify Texture Fallback on empty string (MIN-2)
$empty_tex_test = DMM_Menu_Data_Manager::sanitize_menu_items( array(
	array(
		'db_id'  => 888,
		'title'  => 'Test Empty Tex',
		'visual' => array( 'tex' => '' ),
	),
) );
assert( $empty_tex_test[0]['visual']['tex'] === 'grain', 'Empty texture string must fall back to "grain"' );
echo "  ✓ Verified texture fallback on empty string.\n";

echo "\nALL END-TO-END TESTS PASSED SUCCESSFULLY! (100% green)\n";
