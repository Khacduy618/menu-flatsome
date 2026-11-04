<?php
/**
 * Class DMM_Menu_Data_Manager
 *
 * Manages the hierarchical menu data storage, schema validation, and tree generation.
 *
 * @package Device_Menu_Manager
 */

defined( 'ABSPATH' ) || exit;

class DMM_Menu_Data_Manager {

	/**
	 * Option keys for data storage.
	 */
	const OPTION_MENU_DATA    = 'dmm_menu_data';
	const OPTION_DESKTOP_DATA = 'dmm_desktop_menu_data';
	const OPTION_MOBILE_DATA  = 'dmm_mobile_menu_data';

	/**
	 * Init function.
	 */
	public static function init(): void {
		// Ready for any data lifecycle hooks.
	}

	/**
	 * Get stored menu items for a specific device.
	 *
	 * Unified single responsive menu tree with backward-compatible migration.
	 *
	 * @param string $device Optional 'desktop' or 'mobile'.
	 * @return array
	 */
	public static function get_menu_items( string $device = 'desktop' ): array {
		// 1. Check primary unified option.
		$raw_data = get_option( self::OPTION_MENU_DATA, null );

		// 2. Migration check from legacy desktop option if unified is empty.
		if ( null === $raw_data || false === $raw_data || empty( $raw_data ) ) {
			$legacy = get_option( self::OPTION_DESKTOP_DATA, null );
			if ( ! empty( $legacy ) ) {
				$raw_data = $legacy;
			}
		}

		if ( is_string( $raw_data ) ) {
			$decoded  = json_decode( $raw_data, true );
			$raw_data = is_array( $decoded ) ? $decoded : array();
		}

		if ( empty( $raw_data ) ) {
			return self::get_sample_data();
		}

		$items = self::sanitize_menu_items( $raw_data );

		// Auto-migration: If any sizes_grid item has zero children in database, auto-populate default 14mm-26mm child items.
		$has_sizes_without_children = false;
		$parent_ids                 = array_column( $items, 'menu_item_parent' );

		foreach ( $items as $idx => $item ) {
			if ( ( $item['item_type'] ?? 'link' ) === 'sizes_grid' ) {
				$item_id = (string) $item['db_id'];
				if ( ! in_array( $item_id, $parent_ids, true ) ) {
					$has_sizes_without_children = true;
					$base_url                   = function_exists( 'home_url' ) ? home_url() : 'https://handdn.us';
					$new_children               = array();
					for ( $s = 14; $s <= 26; $s++ ) {
						$new_children[] = array(
							'db_id'            => (int) ( $item['db_id'] . str_pad( (string) $s, 2, '0', STR_PAD_LEFT ) ),
							'title'            => $s . 'mm',
							'url'              => $base_url . '/attribute/lug-width/' . $s . 'mm/',
							'menu_item_parent' => $item_id,
							'item_type'        => 'link',
							'section'          => $item['section'] ?? 'primary',
						);
					}
					array_splice( $items, $idx + 1, 0, $new_children );
					$parent_ids = array_column( $items, 'menu_item_parent' );
				}
			}
		}

		if ( $has_sizes_without_children || count( $items ) !== count( $raw_data ) ) {
			update_option( self::OPTION_MENU_DATA, $items );
			update_option( self::OPTION_DESKTOP_DATA, $items );
			update_option( self::OPTION_MOBILE_DATA, $items );
		}

		return $items;
	}

	/**
	 * Save menu items.
	 *
	 * @param string $device 'desktop' or 'mobile'.
	 * @param array  $items  Array of items.
	 * @return bool
	 */
	public static function save_menu_items( string $device, array $items ): bool {
		$sanitized = self::sanitize_menu_items( $items );

		// Save unified option.
		$updated = update_option( self::OPTION_MENU_DATA, $sanitized );

		// Sync legacy keys for backward compatibility.
		update_option( self::OPTION_DESKTOP_DATA, $sanitized );
		update_option( self::OPTION_MOBILE_DATA, $sanitized );

		return $updated;
	}

	/**
	 * Sanitize and validate array of items against the extended schema.
	 *
	 * Supports section (primary/secondary), item_type (link, heading, sizes_grid),
	 * custom tag, visual banner, and feature card.
	 *
	 * @param array $items Raw items.
	 * @return array
	 */
	public static function sanitize_menu_items( array $items ): array {
		$sanitized = array();
		$seen_ids  = array();

		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$db_id = isset( $item['db_id'] ) ? absint( $item['db_id'] ) : 0;
			if ( ! $db_id ) {
				$db_id = wp_rand( 100000, 999999 );
			}

			// Prevent duplicate items with the same db_id.
			$id_key = (string) $db_id;
			if ( isset( $seen_ids[ $id_key ] ) ) {
				continue;
			}
			$seen_ids[ $id_key ] = true;

			$title = isset( $item['title'] ) ? sanitize_text_field( wp_unslash( $item['title'] ) ) : '';

			// Item type: link | heading | sizes_grid.
			$item_type = isset( $item['item_type'] ) ? sanitize_key( $item['item_type'] ) : 'link';
			if ( ! in_array( $item_type, array( 'link', 'heading', 'sizes_grid' ), true ) ) {
				$item_type = 'link';
			}

			// Headings and sizes_grid default to '#' URL.
			if ( 'heading' === $item_type || 'sizes_grid' === $item_type ) {
				$url = '#';
			} else {
				$url = isset( $item['url'] ) ? esc_url_raw( wp_unslash( $item['url'] ) ) : '#';
				if ( empty( $url ) && isset( $item['url'] ) && '#' === trim( $item['url'] ) ) {
					$url = '#';
				}
			}

			$parent_id = isset( $item['menu_item_parent'] ) ? (string) $item['menu_item_parent'] : '0';
			$parent_id = ( '0' === $parent_id || empty( $parent_id ) ) ? '0' : (string) absint( $parent_id );

			// Section: primary | secondary (default primary).
			$section = isset( $item['section'] ) ? sanitize_key( $item['section'] ) : 'primary';
			if ( 'secondary' !== $section ) {
				$section = 'primary';
			}

			// Custom tag.
			$tag = isset( $item['tag'] ) ? sanitize_text_field( wp_unslash( $item['tag'] ) ) : '';

			// Sanitize classes array.
			$classes = array();
			if ( isset( $item['classes'] ) && is_array( $item['classes'] ) ) {
				foreach ( $item['classes'] as $cls ) {
					$clean_cls = sanitize_html_class( trim( (string) $cls ) );
					if ( '' !== $clean_cls ) {
						$classes[] = $clean_cls;
					}
				}
			}
			if ( empty( $classes ) ) {
				$classes = array( '' );
			}

			// Visual banner configuration.
			$visual = array(
				'enabled'    => false,
				'media_type' => 'texture',
				'image_url'  => '',
				'tex'        => 'grain',
				'tone'       => '#3b2416',
				'kicker'     => '',
				'headline'   => '',
				'cta_text'   => '',
				'cta_url'    => '',
			);
			$valid_textures = array( 'grain', 'scales', 'dots', 'quill', 'weave', 'cross', 'smooth' );
			if ( isset( $item['visual'] ) && is_array( $item['visual'] ) ) {
				$visual['enabled']    = ! empty( $item['visual']['enabled'] );
				$visual['media_type'] = ( isset( $item['visual']['media_type'] ) && 'url' === $item['visual']['media_type'] ) ? 'url' : ( ( isset( $item['visual']['media_type'] ) && 'texture' === $item['visual']['media_type'] ) ? 'texture' : 'upload' );
				$visual['image_url']  = isset( $item['visual']['image_url'] ) ? esc_url_raw( $item['visual']['image_url'] ) : '';
				
				$raw_tex              = isset( $item['visual']['tex'] ) ? sanitize_key( $item['visual']['tex'] ) : '';
				$visual['tex']        = in_array( $raw_tex, $valid_textures, true ) ? $raw_tex : 'grain';

				$visual['tone']       = isset( $item['visual']['tone'] ) ? ( sanitize_hex_color( $item['visual']['tone'] ) ?: '#3b2416' ) : '#3b2416';
				$visual['kicker']     = isset( $item['visual']['kicker'] ) ? sanitize_text_field( wp_unslash( $item['visual']['kicker'] ) ) : '';
				$visual['headline']   = isset( $item['visual']['headline'] ) ? sanitize_text_field( wp_unslash( $item['visual']['headline'] ) ) : '';
				$visual['cta_text']   = isset( $item['visual']['cta_text'] ) ? sanitize_text_field( wp_unslash( $item['visual']['cta_text'] ) ) : '';
				$visual['cta_url']    = isset( $item['visual']['cta_url'] ) ? esc_url_raw( $item['visual']['cta_url'] ) : '';
			}

			// Sub-panel feature card configuration.
			$feature = array(
				'enabled'    => false,
				'media_type' => 'texture',
				'image_url'  => '',
				'tex'        => 'smooth',
				'tone'       => '#3b2416',
				'kicker'     => '',
				'text'       => '',
				'cta_text'   => '',
				'cta_url'    => '',
			);
			if ( isset( $item['feature'] ) && is_array( $item['feature'] ) ) {
				$feature['enabled']    = ! empty( $item['feature']['enabled'] );
				$feature['media_type'] = ( isset( $item['feature']['media_type'] ) && 'url' === $item['feature']['media_type'] ) ? 'url' : ( ( isset( $item['feature']['media_type'] ) && 'texture' === $item['feature']['media_type'] ) ? 'texture' : 'upload' );
				$feature['image_url']  = isset( $item['feature']['image_url'] ) ? esc_url_raw( $item['feature']['image_url'] ) : '';

				$raw_f_tex            = isset( $item['feature']['tex'] ) ? sanitize_key( $item['feature']['tex'] ) : '';
				$feature['tex']        = in_array( $raw_f_tex, $valid_textures, true ) ? $raw_f_tex : 'smooth';

				$feature['tone']       = isset( $item['feature']['tone'] ) ? ( sanitize_hex_color( $item['feature']['tone'] ) ?: '#3b2416' ) : '#3b2416';
				$feature['kicker']     = isset( $item['feature']['kicker'] ) ? sanitize_text_field( wp_unslash( $item['feature']['kicker'] ) ) : '';
				$feature['text']       = isset( $item['feature']['text'] ) ? sanitize_text_field( wp_unslash( $item['feature']['text'] ) ) : '';
				$feature['cta_text']   = isset( $item['feature']['cta_text'] ) ? sanitize_text_field( wp_unslash( $item['feature']['cta_text'] ) ) : '';
				$feature['cta_url']    = isset( $item['feature']['cta_url'] ) ? esc_url_raw( $item['feature']['cta_url'] ) : '';
			}

			$sizes_config = array(
				'title'          => 'Lug width',
				'hint_prefix'    => 'Not sure?',
				'hint_suffix'    => 'in two minutes.',
				'hint_link_text' => 'Measure your lugs',
				'hint_link_url'  => 'https://handdn.us/support-center/how-to-measure-exactly-watch-strap/',
			);
			if ( isset( $item['sizes_config'] ) && is_array( $item['sizes_config'] ) ) {
				$sizes_config['title']          = ! empty( $item['sizes_config']['title'] ) ? sanitize_text_field( $item['sizes_config']['title'] ) : 'Lug width';
				$sizes_config['hint_prefix']    = ! empty( $item['sizes_config']['hint_prefix'] ) ? sanitize_text_field( $item['sizes_config']['hint_prefix'] ) : 'Not sure?';
				$sizes_config['hint_suffix']    = ! empty( $item['sizes_config']['hint_suffix'] ) ? sanitize_text_field( $item['sizes_config']['hint_suffix'] ) : 'in two minutes.';
				$sizes_config['hint_link_text'] = ! empty( $item['sizes_config']['hint_link_text'] ) ? sanitize_text_field( $item['sizes_config']['hint_link_text'] ) : 'Measure your lugs';
				$sizes_config['hint_link_url']  = ! empty( $item['sizes_config']['hint_link_url'] ) ? esc_url_raw( $item['sizes_config']['hint_link_url'] ) : 'https://handdn.us/support-center/how-to-measure-exactly-watch-strap/';
			}

			$sanitized[] = array(
				'db_id'            => $db_id,
				'title'            => $title,
				'url'              => $url,
				'menu_item_parent' => $parent_id,
				'section'          => $section,
				'item_type'        => $item_type,
				'tag'              => $tag,
				'classes'          => array_values( $classes ),
				'visual'           => $visual,
				'feature'          => $feature,
				'sizes_config'     => $sizes_config,
			);
		}

		// Ensure child items inherit section from their root ancestor.
		$items_by_id = array();
		foreach ( $sanitized as $it ) {
			$items_by_id[ (string) $it['db_id'] ] = $it;
		}

		foreach ( $sanitized as &$it ) {
			if ( '0' !== $it['menu_item_parent'] ) {
				$cur = $it['menu_item_parent'];
				$visited = array( (string) $it['db_id'] => true );
				while ( '0' !== $cur && isset( $items_by_id[ $cur ] ) && ! isset( $visited[ $cur ] ) ) {
					$visited[ $cur ] = true;
					if ( '0' === $items_by_id[ $cur ]['menu_item_parent'] ) {
						$it['section'] = $items_by_id[ $cur ]['section'];
						break;
					}
					$cur = $items_by_id[ $cur ]['menu_item_parent'];
				}
			}
		}
		unset( $it );

		return $sanitized;
	}

	/**
	 * Get drawer settings (promo banner, trustpilot, woocommerce links).
	 *
	 * @return array
	 */
	public static function get_drawer_settings(): array {
		$drawer = get_option( 'dmm_drawer_settings', null );
		if ( ! is_array( $drawer ) ) {
			$drawer = array();
		}

		$settings = get_option( 'dmm_settings', array() );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		$root_promo    = $settings['root_promo'] ?? ( $settings['drawer_promo'] ?? array() );
		$drawer_footer = $settings['drawer_footer'] ?? array();

		$defaults = array(
			'promo_enabled'        => 1,
			'promo_media_type'     => 'texture',
			'promo_texture'        => 'grain',
			'promo_tone'           => '#8a4a26',
			'promo_image_url'      => '',
			'promo_kicker'         => '3D Custom',
			'promo_headline'       => 'Design your strap in 3D, then we cut and stitch it by hand.',
			'promo_cta_text'       => 'Start designing',
			'promo_cta_url'        => 'https://handdn.us/product/3d-custom-watch-strap/',
			'promo_show_strap'     => 1,
			'trustpilot_enabled'   => 1,
			'trustpilot_auto'      => 0,
			'trustpilot_rating'    => '4.7',
			'trustpilot_count'     => '619',
			'trustpilot_url'       => 'https://www.trustpilot.com/review/handdn.com',
			'account_link_enabled' => 1,
			'track_order_enabled'  => 1,
			'contact_link_enabled' => 1,
			'wc_myaccount_url'     => 'https://handdn.us/my-account/',
			'wc_track_url'         => 'https://handdn.us/my-account/track-order/',
			'wc_contact_url'       => 'https://handdn.us/contact/',
			'wc_instagram_url'     => 'https://www.instagram.com/handdn.store/',
		);

		// Synchronize values from dmm_settings if set.
		if ( isset( $root_promo['enabled'] ) ) {
			$drawer['promo_enabled'] = ! empty( $root_promo['enabled'] ) ? 1 : 0;
		}
		if ( isset( $root_promo['media_type'] ) ) {
			$drawer['promo_media_type'] = $root_promo['media_type'];
		}
		if ( isset( $root_promo['tex'] ) ) {
			$drawer['promo_texture'] = $root_promo['tex'];
		}
		if ( isset( $root_promo['tone'] ) ) {
			$drawer['promo_tone'] = $root_promo['tone'];
		}
		if ( isset( $root_promo['image_url'] ) ) {
			$drawer['promo_image_url'] = $root_promo['image_url'];
		}
		if ( isset( $root_promo['kicker'] ) && '' !== $root_promo['kicker'] ) {
			$drawer['promo_kicker'] = $root_promo['kicker'];
		}
		if ( isset( $root_promo['headline'] ) && '' !== $root_promo['headline'] ) {
			$drawer['promo_headline'] = $root_promo['headline'];
		}
		if ( isset( $root_promo['cta_text'] ) && '' !== $root_promo['cta_text'] ) {
			$drawer['promo_cta_text'] = $root_promo['cta_text'];
		}
		if ( isset( $root_promo['cta_url'] ) && '' !== $root_promo['cta_url'] ) {
			$drawer['promo_cta_url'] = $root_promo['cta_url'];
		}
		if ( isset( $root_promo['show_strap_svg'] ) ) {
			$drawer['promo_show_strap'] = ! empty( $root_promo['show_strap_svg'] ) ? 1 : 0;
		}

		if ( isset( $drawer_footer['trustpilot_enabled'] ) ) {
			$drawer['trustpilot_enabled'] = ! empty( $drawer_footer['trustpilot_enabled'] ) ? 1 : 0;
		}
		if ( isset( $drawer_footer['trustpilot_auto'] ) ) {
			$drawer['trustpilot_auto'] = ! empty( $drawer_footer['trustpilot_auto'] ) ? 1 : 0;
		}
		if ( ! empty( $drawer_footer['trustpilot_rating'] ) ) {
			$drawer['trustpilot_rating'] = $drawer_footer['trustpilot_rating'];
		}
		if ( ! empty( $drawer_footer['trustpilot_count'] ) ) {
			$drawer['trustpilot_count'] = $drawer_footer['trustpilot_count'];
		}
		if ( ! empty( $drawer_footer['trustpilot_url'] ) ) {
			$drawer['trustpilot_url'] = $drawer_footer['trustpilot_url'];
		}
		if ( isset( $drawer_footer['account_link_enabled'] ) ) {
			$drawer['account_link_enabled'] = ! empty( $drawer_footer['account_link_enabled'] ) ? 1 : 0;
		}
		if ( isset( $drawer_footer['track_order_enabled'] ) ) {
			$drawer['track_order_enabled'] = ! empty( $drawer_footer['track_order_enabled'] ) ? 1 : 0;
		}
		if ( isset( $drawer_footer['contact_link_enabled'] ) ) {
			$drawer['contact_link_enabled'] = ! empty( $drawer_footer['contact_link_enabled'] ) ? 1 : 0;
		}
		if ( ! empty( $drawer_footer['instagram_url'] ) ) {
			$drawer['wc_instagram_url'] = $drawer_footer['instagram_url'];
		}

		return wp_parse_args( $drawer, $defaults );
	}

	/**
	 * Retrieve Trustpilot reviews data from active plugin or manual settings.
	 *
	 * @return array
	 */
	public static function get_trustpilot_data(): array {
		$drawer = self::get_drawer_settings();

		$default = array(
			'enabled' => ! isset( $drawer['trustpilot_enabled'] ) || ! empty( $drawer['trustpilot_enabled'] ),
			'rating'  => isset( $drawer['trustpilot_rating'] ) && '' !== trim( (string) $drawer['trustpilot_rating'] ) ? sanitize_text_field( $drawer['trustpilot_rating'] ) : '4.7',
			'count'   => isset( $drawer['trustpilot_count'] ) && '' !== trim( (string) $drawer['trustpilot_count'] ) ? sanitize_text_field( $drawer['trustpilot_count'] ) : '619',
			'url'     => isset( $drawer['trustpilot_url'] ) && '' !== trim( (string) $drawer['trustpilot_url'] ) ? esc_url_raw( $drawer['trustpilot_url'] ) : 'https://www.trustpilot.com/review/handdn.com',
			'source'  => 'manual',
		);

		// If auto-detect from Trustpilot plugin is requested.
		if ( ! empty( $drawer['trustpilot_auto'] ) ) {
			// Check official Trustpilot plugin options.
			$tp_opts = get_option( 'trustpilot_settings', null );
			if ( empty( $tp_opts ) ) {
				$tp_opts = get_option( 'trustpilot_woocommerce_settings', null );
			}

			if ( is_array( $tp_opts ) && ! empty( $tp_opts['trustScore'] ) ) {
				$default['rating'] = (string) $tp_opts['trustScore'];
				$default['source'] = 'plugin';
				if ( ! empty( $tp_opts['totalNumberOfReviews'] ) ) {
					$default['count'] = (string) $tp_opts['totalNumberOfReviews'];
				}
				if ( ! empty( $tp_opts['businessUnitId'] ) ) {
					$default['url'] = 'https://www.trustpilot.com/review/' . sanitize_text_field( $tp_opts['businessUnitId'] );
				}
			}
		}

		return $default;
	}

	/**
	 * Check connection status with the official Trustpilot plugin.
	 *
	 * @return array Status array with keys 'connected', 'rating', 'count', 'message'.
	 */
	public static function get_trustpilot_status(): array {
		$tp_opts = get_option( 'trustpilot_settings', null );
		if ( empty( $tp_opts ) ) {
			$tp_opts = get_option( 'trustpilot_woocommerce_settings', null );
		}

		if ( is_array( $tp_opts ) && ! empty( $tp_opts['trustScore'] ) ) {
			return array(
				'connected' => true,
				'rating'    => (string) $tp_opts['trustScore'],
				'count'     => ! empty( $tp_opts['totalNumberOfReviews'] ) ? (string) $tp_opts['totalNumberOfReviews'] : '',
				'message'   => __( 'Trustpilot plugin connected successfully.', 'device-menu-manager' ),
			);
		}

		return array(
			'connected' => false,
			'rating'    => '',
			'count'     => '',
			'message'   => __( 'Chưa kết nối được với plugin Trustpilot: Không tìm thấy plugin Trustpilot đang hoạt động hoặc chưa cấu hình API. Hệ thống sẽ tự động sử dụng điểm số nhập thủ công bên dưới.', 'device-menu-manager' ),
		);
	}

	/**
	 * Convert flat menu list into a recursive hierarchical tree structure.
	 *
	 * Propagates root section (primary/secondary) down to all descendant children.
	 *
	 * @param array  $items              Flat array of menu items.
	 * @param string $parent_id          Current parent ID to query ('0' for root).
	 * @param string $inherited_section  Section inherited from root ancestor ('primary' or 'secondary').
	 * @return array
	 */
	public static function build_tree( array $items, string $parent_id = '0', string $inherited_section = 'primary' ): array {
		$branch = array();

		foreach ( $items as $item ) {
			if ( (string) $item['menu_item_parent'] === (string) $parent_id ) {
				$current_section = ( '0' === (string) $parent_id ) ? ( $item['section'] ?? 'primary' ) : $inherited_section;
				$item['section'] = $current_section;

				$children = self::build_tree( $items, (string) $item['db_id'], $current_section );
				if ( ! empty( $children ) ) {
					$item['children'] = $children;
				}
				$branch[] = $item;
			}
		}

		return $branch;
	}

	/**
	 * Import and map a core WordPress Nav Menu to our custom schema.
	 *
	 * @param int $menu_id WordPress Nav Menu term ID.
	 * @return array
	 */
	public static function import_from_wp_nav_menu( int $menu_id ): array {
		$menu_items = wp_get_nav_menu_items( $menu_id );
		if ( ! is_array( $menu_items ) || empty( $menu_items ) ) {
			return array();
		}

		$converted = array();
		foreach ( $menu_items as $post ) {
			$classes = is_array( $post->classes ) ? array_filter( $post->classes ) : array();

			$converted[] = array(
				'db_id'            => (int) $post->ID,
				'title'            => (string) $post->title,
				'url'              => (string) $post->url,
				'menu_item_parent' => (string) ( $post->menu_item_parent ? $post->menu_item_parent : '0' ),
				'classes'          => ! empty( $classes ) ? array_values( $classes ) : array( '' ),
			);
		}

		return self::sanitize_menu_items( $converted );
	}

	/**
	 * Seed initial sample data if options do not exist.
	 */
	public static function seed_initial_data(): void {
		if ( false === get_option( self::OPTION_MENU_DATA ) ) {
			update_option( self::OPTION_MENU_DATA, self::get_sample_data() );
		}
		if ( false === get_option( self::OPTION_DESKTOP_DATA ) ) {
			update_option( self::OPTION_DESKTOP_DATA, self::get_sample_data() );
		}
		if ( false === get_option( self::OPTION_MOBILE_DATA ) ) {
			update_option( self::OPTION_MOBILE_DATA, self::get_sample_data() );
		}
	}

	/**
	 * Return the HandDN default dataset matching the luxury Prototype architecture.
	 *
	 * Includes Primary & Secondary root items, Group Headings, Sizes Grid,
	 * and pre-configured Visual Banners & Feature Cards.
	 *
	 * @return array
	 */
	public static function get_sample_data(): array {
		$B = 'https://handdn.us';

		return array(
			// ================= PRIMARY ITEMS =================
			// 1. Watch Straps (Root Level 0)
			array(
				'db_id'            => 10001,
				'title'            => 'Watch Straps',
				'url'              => $B . '/shop/',
				'menu_item_parent' => '0',
				'section'          => 'primary',
				'item_type'        => 'link',
				'tag'              => '',
				'classes'          => array( '' ),
				'visual'           => array(
					'enabled'    => true,
					'media_type' => 'texture',
					'tex'        => 'grain',
					'tone'       => '#6b3b20',
					'kicker'     => 'Watch Straps',
					'headline'   => 'Hand-cut from the cleanest, most even-grained part of the hide.',
					'cta_text'   => 'Shop all straps',
					'cta_url'    => $B . '/shop/',
				),
				'feature'          => array(
					'enabled'    => true,
					'media_type' => 'image',
					'image_url'  => '/wp-content/uploads/2026/10/20240906-112342-450x600-1.jpeg',
					'tex'        => 'smooth',
					'tone'       => '#6b3b20',
					'kicker'     => 'Leather collection',
					'text'       => 'Every leather we cut, from Epsom to shell cordovan.',
					'cta_text'   => 'Browse leathers',
					'cta_url'    => $B . '/shop/collection-leather/',
				),
			),
			// Direct links under Watch Straps
			array(
				'db_id'            => 10002,
				'title'            => 'All watch straps',
				'url'              => $B . '/shop/',
				'menu_item_parent' => '10001',
				'item_type'        => 'link',
			),
			array(
				'db_id'            => 10003,
				'title'            => 'In stock, ready to ship',
				'url'              => $B . '/shop/available-straps/',
				'menu_item_parent' => '10001',
				'item_type'        => 'link',
			),
			array(
				'db_id'            => 10004,
				'title'            => 'Leather collection',
				'url'              => $B . '/shop/collection-leather/',
				'menu_item_parent' => '10001',
				'item_type'        => 'link',
			),

			// Group 1: BY MATERIAL
			array(
				'db_id'            => 10100,
				'title'            => 'BY MATERIAL',
				'url'              => '#',
				'menu_item_parent' => '10001',
				'item_type'        => 'heading',
			),
			// Exotic Leathers under BY MATERIAL
			array(
				'db_id'            => 10110,
				'title'            => 'Exotic leathers',
				'url'              => $B . '/shop/exotic-leather-watch-straps/',
				'menu_item_parent' => '10100',
				'item_type'        => 'link',
				'visual'           => array(
					'enabled'    => true,
					'media_type' => 'texture',
					'tex'        => 'scales',
					'tone'       => '#3b2416',
					'kicker'     => 'Exotic leathers',
					'headline'   => '440 straps in alligator, ostrich, stingray and more.',
					'cta_text'   => 'Shop exotic leathers',
					'cta_url'    => $B . '/shop/exotic-leather-watch-straps/',
				),
			),
			// Sub-items of Exotic Leathers
			array(
				'db_id'            => 10111,
				'title'            => 'All exotic leathers',
				'url'              => $B . '/shop/exotic-leather-watch-straps/',
				'menu_item_parent' => '10110',
				'item_type'        => 'link',
			),
			array(
				'db_id'            => 10112,
				'title'            => 'Alligator',
				'url'              => $B . '/attribute/leather/alligator/',
				'menu_item_parent' => '10110',
				'item_type'        => 'link',
				'tag'              => 'HOT',
				'visual'           => array(
					'enabled'    => true,
					'tex'        => 'scales',
					'tone'       => '#3b2416',
					'kicker'     => 'Exotic leathers',
					'headline'   => 'Mississippiensis American Alligator belly cut.',
					'cta_text'   => 'Shop Alligator',
					'cta_url'    => $B . '/attribute/leather/alligator/',
				),
			),
			array(
				'db_id'            => 10113,
				'title'            => 'Double Hornback Alligator',
				'url'              => $B . '/attribute/leather/double-hornback-alligator/',
				'menu_item_parent' => '10110',
				'item_type'        => 'link',
			),
			array(
				'db_id'            => 10114,
				'title'            => 'Shell Cordovan',
				'url'              => $B . '/attribute/leather/shell-cordovan/',
				'menu_item_parent' => '10110',
				'item_type'        => 'link',
				'visual'           => array(
					'enabled'    => true,
					'tex'        => 'smooth',
					'tone'       => '#1f2a3d',
					'kicker'     => 'Exotic leathers',
					'headline'   => 'Equine leather from Horween and Rocado tanneries.',
					'cta_text'   => 'Shop Shell Cordovan',
					'cta_url'    => $B . '/attribute/leather/shell-cordovan/',
				),
			),
			array(
				'db_id'            => 10115,
				'title'            => 'Stingray',
				'url'              => $B . '/attribute/leather/stingray/',
				'menu_item_parent' => '10110',
				'item_type'        => 'link',
				'visual'           => array(
					'enabled'    => true,
					'tex'        => 'dots',
					'tone'       => '#22304a',
					'kicker'     => 'Exotic leathers',
					'headline'   => 'Polished pearl stingray skin with central crown.',
					'cta_text'   => 'Shop Stingray',
					'cta_url'    => $B . '/attribute/leather/stingray/',
				),
			),
			array(
				'db_id'            => 10116,
				'title'            => 'Ostrich',
				'url'              => $B . '/attribute/leather/ostrich/',
				'menu_item_parent' => '10110',
				'item_type'        => 'link',
				'tag'              => 'NEW',
			),
			array(
				'db_id'            => 10117,
				'title'            => 'Lizard',
				'url'              => $B . '/attribute/leather/lizard/',
				'menu_item_parent' => '10110',
				'item_type'        => 'link',
			),

			// Regular Leathers under BY MATERIAL
			array(
				'db_id'            => 10120,
				'title'            => 'Regular leathers',
				'url'              => $B . '/shop/regular-leather-watch-straps/',
				'menu_item_parent' => '10100',
				'item_type'        => 'link',
				'visual'           => array(
					'enabled'    => true,
					'media_type' => 'texture',
					'tex'        => 'grain',
					'tone'       => '#1f2a3d',
					'kicker'     => 'Regular leathers',
					'headline'   => 'Calfskin from Badalassi Carlo, Alran and Handdn patina.',
					'cta_text'   => 'Shop regular leathers',
					'cta_url'    => $B . '/shop/regular-leather-watch-straps/',
				),
			),
			array(
				'db_id'            => 10121,
				'title'            => 'All regular leathers',
				'url'              => $B . '/shop/regular-leather-watch-straps/',
				'menu_item_parent' => '10120',
				'item_type'        => 'link',
			),
			// Heading under Regular Leathers: Calfskin
			array(
				'db_id'            => 10122,
				'title'            => 'Calfskin',
				'url'              => '#',
				'menu_item_parent' => '10120',
				'item_type'        => 'heading',
			),
			array(
				'db_id'            => 10123,
				'title'            => 'Box Calfskin',
				'url'              => $B . '/attribute/leather/box-calfskin/',
				'menu_item_parent' => '10122',
				'item_type'        => 'link',
			),
			array(
				'db_id'            => 10124,
				'title'            => 'Epsom',
				'url'              => $B . '/attribute/leather/epsom/',
				'menu_item_parent' => '10122',
				'item_type'        => 'link',
			),
			array(
				'db_id'            => 10125,
				'title'            => 'Saffiano',
				'url'              => $B . '/attribute/leather/saffiano/',
				'menu_item_parent' => '10122',
				'item_type'        => 'link',
			),
			array(
				'db_id'            => 10126,
				'title'            => 'Togo',
				'url'              => $B . '/attribute/leather/togo/',
				'menu_item_parent' => '10122',
				'item_type'        => 'link',
			),

			// Non-leathers under BY MATERIAL
			array(
				'db_id'            => 10130,
				'title'            => 'Non-leathers',
				'url'              => $B . '/shop/non-leather-watch-straps/',
				'menu_item_parent' => '10100',
				'item_type'        => 'link',
				'visual'           => array(
					'enabled'    => true,
					'tex'        => 'weave',
					'tone'       => '#4b4f36',
					'kicker'     => 'Non-leathers',
					'headline'   => 'Alcantara, canvas and sailcloth for lighter wear.',
					'cta_text'   => 'Shop non-leathers',
					'cta_url'    => $B . '/shop/non-leather-watch-straps/',
				),
			),
			array(
				'db_id'            => 10131,
				'title'            => 'Alcantara',
				'url'              => $B . '/attribute/leather/alcantara/',
				'menu_item_parent' => '10130',
				'item_type'        => 'link',
			),
			array(
				'db_id'            => 10132,
				'title'            => 'Sailcloth Rubber',
				'url'              => $B . '/attribute/leather/sailcloth-rubber/',
				'menu_item_parent' => '10130',
				'item_type'        => 'link',
				'tag'              => 'NEW',
			),

			// Group 2: BY FIT
			array(
				'db_id'            => 10200,
				'title'            => 'BY FIT',
				'url'              => '#',
				'menu_item_parent' => '10001',
				'item_type'        => 'heading',
			),
			// Strap Styles
			array(
				'db_id'            => 10210,
				'title'            => 'Strap styles',
				'url'              => $B . '/shop/watch-strap-style/',
				'menu_item_parent' => '10200',
				'item_type'        => 'link',
				'visual'           => array(
					'enabled'    => true,
					'tex'        => 'smooth',
					'tone'       => '#9a6234',
					'kicker'     => 'Strap styles',
					'headline'   => 'Classic, curved end, bund, rally and NATO.',
					'cta_text'   => 'See every style',
					'cta_url'    => $B . '/shop/watch-strap-style/',
				),
			),
			array(
				'db_id'            => 10211,
				'title'            => 'All strap styles',
				'url'              => $B . '/shop/watch-strap-style/',
				'menu_item_parent' => '10210',
				'item_type'        => 'link',
			),
			// Heading under Strap styles: Construction
			array(
				'db_id'            => 10212,
				'title'            => 'CONSTRUCTION',
				'url'              => '#',
				'menu_item_parent' => '10210',
				'item_type'        => 'heading',
			),
			array(
				'db_id'            => 10213,
				'title'            => 'Classic',
				'url'              => $B . '/shop/classic-watch-straps/',
				'menu_item_parent' => '10212',
				'item_type'        => 'link',
			),
			array(
				'db_id'            => 10214,
				'title'            => 'Curved End',
				'url'              => $B . '/shop/curved-end-watch-strap/',
				'menu_item_parent' => '10212',
				'item_type'        => 'link',
			),
			array(
				'db_id'            => 10215,
				'title'            => 'Bund Strap',
				'url'              => $B . '/shop/bund-strap/',
				'menu_item_parent' => '10212',
				'item_type'        => 'link',
			),
			array(
				'db_id'            => 10216,
				'title'            => 'Rally',
				'url'              => $B . '/shop/rally-watch-straps/',
				'menu_item_parent' => '10212',
				'item_type'        => 'link',
			),
			// Heading under Strap styles: Color and finish
			array(
				'db_id'            => 10217,
				'title'            => 'COLOR AND FINISH',
				'url'              => '#',
				'menu_item_parent' => '10210',
				'item_type'        => 'heading',
			),
			array(
				'db_id'            => 10218,
				'title'            => 'Duocolor',
				'url'              => $B . '/product-tag/doucolor/',
				'menu_item_parent' => '10217',
				'item_type'        => 'link',
			),
			array(
				'db_id'            => 10219,
				'title'            => 'Patina Veg-Tanned',
				'url'              => $B . '/shop/patina-style/',
				'menu_item_parent' => '10217',
				'item_type'        => 'link',
			),

			// By Watch Brand under BY FIT
			array(
				'db_id'            => 10220,
				'title'            => 'By watch brand',
				'url'              => $B . '/shop/apple-watch-straps/',
				'menu_item_parent' => '10200',
				'item_type'        => 'link',
				'visual'           => array(
					'enabled'    => true,
					'tex'        => 'grain',
					'tone'       => '#1d1b1a',
					'kicker'     => 'By watch brand',
					'headline'   => '351 Apple Watch bands, plus Panerai, Baltic and Samsung.',
					'cta_text'   => 'Shop Apple Watch',
					'cta_url'    => $B . '/shop/apple-watch-straps/',
				),
			),
			array(
				'db_id'            => 10223,
				'title'            => 'Apple Watch',
				'url'              => '#',
				'menu_item_parent' => '10220',
				'item_type'        => 'heading',
			),
			array(
				'db_id'            => 10224,
				'title'            => 'All Apple Watch bands',
				'url'              => $B . '/shop/apple-watch-straps/',
				'menu_item_parent' => '10223',
				'item_type'        => 'link',
			),
			array(
				'db_id'            => 10221,
				'title'            => 'Apple Watch Ultra',
				'url'              => $B . '/shop/apple-watch-ultra-band/',
				'menu_item_parent' => '10223',
				'item_type'        => 'link',
			),
			array(
				'db_id'            => 10225,
				'title'            => 'Apple Watch Series',
				'url'              => $B . '/shop/apple-watch-series-band/',
				'menu_item_parent' => '10223',
				'item_type'        => 'link',
			),
			array(
				'db_id'            => 10226,
				'title'            => 'Other brands',
				'url'              => '#',
				'menu_item_parent' => '10220',
				'item_type'        => 'heading',
			),
			array(
				'db_id'            => 10227,
				'title'            => 'Samsung Watch',
				'url'              => $B . '/shop/samsung-watch-bands/',
				'menu_item_parent' => '10226',
				'item_type'        => 'link',
			),
			array(
				'db_id'            => 10228,
				'title'            => 'Baltic',
				'url'              => $B . '/shop/baltic-watch-strap/',
				'menu_item_parent' => '10226',
				'item_type'        => 'link',
			),
			array(
				'db_id'            => 10222,
				'title'            => 'Panerai (PAM)',
				'url'              => $B . '/shop/panerai-style-watch-straps/',
				'menu_item_parent' => '10226',
				'item_type'        => 'link',
			),

			// Shop by lug width (Sizes Grid) under BY FIT
			array(
				'db_id'            => 10230,
				'title'            => 'Shop by lug width',
				'url'              => '#',
				'menu_item_parent' => '10200',
				'item_type'        => 'sizes_grid',
				'visual'           => array(
					'enabled'    => true,
					'tex'        => 'smooth',
					'tone'       => '#b07a3a',
					'kicker'     => 'Lug width',
					'headline'   => 'Every strap from 14 to 26mm, in 1mm steps.',
					'cta_text'   => 'How to measure',
					'cta_url'    => $B . '/support-center/how-to-measure-exactly-watch-strap/',
				),
			),

			// Child items under Shop by lug width (Dynamic grid pill sizes: 14mm to 26mm)
			array(
				'db_id'            => 10231,
				'title'            => '14mm',
				'url'              => $B . '/attribute/lug-width/14mm/',
				'menu_item_parent' => '10230',
				'item_type'        => 'link',
			),
			array(
				'db_id'            => 10232,
				'title'            => '15mm',
				'url'              => $B . '/attribute/lug-width/15mm/',
				'menu_item_parent' => '10230',
				'item_type'        => 'link',
			),
			array(
				'db_id'            => 10233,
				'title'            => '16mm',
				'url'              => $B . '/attribute/lug-width/16mm/',
				'menu_item_parent' => '10230',
				'item_type'        => 'link',
			),
			array(
				'db_id'            => 10234,
				'title'            => '17mm',
				'url'              => $B . '/attribute/lug-width/17mm/',
				'menu_item_parent' => '10230',
				'item_type'        => 'link',
			),
			array(
				'db_id'            => 10235,
				'title'            => '18mm',
				'url'              => $B . '/attribute/lug-width/18mm/',
				'menu_item_parent' => '10230',
				'item_type'        => 'link',
			),
			array(
				'db_id'            => 10236,
				'title'            => '19mm',
				'url'              => $B . '/attribute/lug-width/19mm/',
				'menu_item_parent' => '10230',
				'item_type'        => 'link',
			),
			array(
				'db_id'            => 10237,
				'title'            => '20mm',
				'url'              => $B . '/attribute/lug-width/20mm/',
				'menu_item_parent' => '10230',
				'item_type'        => 'link',
			),
			array(
				'db_id'            => 10238,
				'title'            => '21mm',
				'url'              => $B . '/attribute/lug-width/21mm/',
				'menu_item_parent' => '10230',
				'item_type'        => 'link',
			),
			array(
				'db_id'            => 10239,
				'title'            => '22mm',
				'url'              => $B . '/attribute/lug-width/22mm/',
				'menu_item_parent' => '10230',
				'item_type'        => 'link',
			),
			array(
				'db_id'            => 10240,
				'title'            => '23mm',
				'url'              => $B . '/attribute/lug-width/23mm/',
				'menu_item_parent' => '10230',
				'item_type'        => 'link',
			),
			array(
				'db_id'            => 10241,
				'title'            => '24mm',
				'url'              => $B . '/attribute/lug-width/24mm/',
				'menu_item_parent' => '10230',
				'item_type'        => 'link',
			),
			array(
				'db_id'            => 10242,
				'title'            => '25mm',
				'url'              => $B . '/attribute/lug-width/25mm/',
				'menu_item_parent' => '10230',
				'item_type'        => 'link',
			),
			array(
				'db_id'            => 10243,
				'title'            => '26mm',
				'url'              => $B . '/attribute/lug-width/26mm/',
				'menu_item_parent' => '10230',
				'item_type'        => 'link',
			),

			// 2. Bespoke (Root Level 0)
			array(
				'db_id'            => 20001,
				'title'            => 'Bespoke',
				'url'              => $B . '/product/bespoke-watch-strap/',
				'menu_item_parent' => '0',
				'section'          => 'primary',
				'item_type'        => 'link',
				'visual'           => array(
					'enabled'    => true,
					'tex'        => 'scales',
					'tone'       => '#5a2f1b',
					'kicker'     => 'Bespoke',
					'headline'   => '151 customer bespoke straps to start your own from.',
					'cta_text'   => 'See the gallery',
					'cta_url'    => $B . '/shop/customer-bespoke-straps/',
				),
				'feature'          => array(
					'enabled'    => true,
					'media_type' => 'image',
					'image_url'  => '/wp-content/uploads/2026/10/20240906-112342-450x600-1.jpeg',
					'tex'        => 'scales',
					'tone'       => '#3b2416',
					'kicker'     => 'Craft',
					'text'       => 'Saddle-stitched by hand and checked three times before it ships.',
					'cta_text'   => 'Read the process',
					'cta_url'    => $B . '/craftsmanship-story/',
				),
			),
			array(
				'db_id'            => 20002,
				'title'            => '3D custom watch strap',
				'url'              => $B . '/product/3d-custom-watch-strap/',
				'menu_item_parent' => '20001',
				'item_type'        => 'link',
				'tag'              => 'Must try',
			),
			array(
				'db_id'            => 20003,
				'title'            => 'Custom watch strap',
				'url'              => $B . '/product/bespoke-watch-strap/',
				'menu_item_parent' => '20001',
				'item_type'        => 'link',
			),
			array(
				'db_id'            => 20010,
				'title'            => 'Before you order',
				'url'              => '#',
				'menu_item_parent' => '20001',
				'item_type'        => 'heading',
			),
			array(
				'db_id'            => 20004,
				'title'            => 'Customer bespoke straps',
				'url'              => $B . '/shop/customer-bespoke-straps/',
				'menu_item_parent' => '20010',
				'item_type'        => 'link',
			),
			array(
				'db_id'            => 20005,
				'title'            => 'How to measure',
				'url'              => $B . '/support-center/how-to-measure-exactly-watch-strap/',
				'menu_item_parent' => '20010',
				'item_type'        => 'link',
			),

			// 3. Apple Watch Bands (Root Level 0)
			array(
				'db_id'            => 30001,
				'title'            => 'Apple Watch Bands',
				'url'              => $B . '/shop/apple-watch-straps/',
				'menu_item_parent' => '0',
				'section'          => 'primary',
				'item_type'        => 'link',
				'visual'           => array(
					'enabled'    => true,
					'tex'        => 'grain',
					'tone'       => '#2a2624',
					'kicker'     => 'Apple Watch Bands',
					'headline'   => 'Leather bands for Ultra and Series, made to order.',
					'cta_text'   => 'Shop Apple Watch',
					'cta_url'    => $B . '/shop/apple-watch-straps/',
				),
			),
			array(
				'db_id'            => 30002,
				'title'            => 'All Apple Watch bands',
				'url'              => $B . '/shop/apple-watch-straps/',
				'menu_item_parent' => '30001',
				'item_type'        => 'link',
			),
			array(
				'db_id'            => 30003,
				'title'            => 'Apple Watch Ultra',
				'url'              => $B . '/shop/apple-watch-ultra-band/',
				'menu_item_parent' => '30001',
				'item_type'        => 'link',
			),
			array(
				'db_id'            => 30004,
				'title'            => 'Apple Watch Series',
				'url'              => $B . '/shop/apple-watch-series-band/',
				'menu_item_parent' => '30001',
				'item_type'        => 'link',
			),

			// 4. In Stock (Root Level 0)
			array(
				'db_id'            => 40001,
				'title'            => 'In Stock',
				'url'              => $B . '/shop/available-straps/',
				'menu_item_parent' => '0',
				'section'          => 'primary',
				'item_type'        => 'link',
			),

			// 5. Gifts (Root Level 0)
			array(
				'db_id'            => 50001,
				'title'            => 'Gifts',
				'url'              => $B . '/shop/gift/',
				'menu_item_parent' => '0',
				'section'          => 'primary',
				'item_type'        => 'link',
				'visual'           => array(
					'enabled'    => true,
					'tex'        => 'smooth',
					'tone'       => '#5c1f24',
					'kicker'     => 'Gifts',
					'headline'   => 'Gift cards and straps ready to give.',
					'cta_text'   => 'Shop gifts',
					'cta_url'    => $B . '/shop/gift/',
				),
			),
			array(
				'db_id'            => 50002,
				'title'            => 'All gifts',
				'url'              => $B . '/shop/gift/',
				'menu_item_parent' => '50001',
				'item_type'        => 'link',
			),
			array(
				'db_id'            => 50003,
				'title'            => 'Gift card',
				'url'              => $B . '/shop/gift-card/',
				'menu_item_parent' => '50001',
				'item_type'        => 'link',
			),

			// ================= SECONDARY ITEMS =================
			// 6. Discover Handdn (Root Level 0 - Secondary)
			array(
				'db_id'            => 60001,
				'title'            => 'Discover Handdn',
				'url'              => $B . '/about-us/',
				'menu_item_parent' => '0',
				'section'          => 'secondary',
				'item_type'        => 'link',
				'visual'           => array(
					'enabled'    => true,
					'tex'        => 'grain',
					'tone'       => '#7a5a3a',
					'kicker'     => 'Discover Handdn',
					'headline'   => 'Rated 4.7 by 619 reviewers on Trustpilot.',
					'cta_text'   => 'Read our story',
					'cta_url'    => $B . '/about-us/',
				),
			),
			array(
				'db_id'            => 60002,
				'title'            => 'About Handdn',
				'url'              => $B . '/about-us/',
				'menu_item_parent' => '60001',
				'section'          => 'secondary',
				'item_type'        => 'link',
			),
			array(
				'db_id'            => 60003,
				'title'            => 'Craftsmanship stories',
				'url'              => $B . '/craftsmanship-story/',
				'menu_item_parent' => '60001',
				'section'          => 'secondary',
				'item_type'        => 'link',
			),
			array(
				'db_id'            => 60004,
				'title'            => 'Journal',
				'url'              => $B . '/blog/',
				'menu_item_parent' => '60001',
				'section'          => 'secondary',
				'item_type'        => 'link',
			),
			array(
				'db_id'            => 60005,
				'title'            => 'Customer feedback',
				'url'              => $B . '/handdn-feedback/',
				'menu_item_parent' => '60001',
				'section'          => 'secondary',
				'item_type'        => 'link',
			),
			array(
				'db_id'            => 60006,
				'title'            => 'Trustpilot reviews',
				'url'              => 'https://www.trustpilot.com/review/handdn.com',
				'menu_item_parent' => '60001',
				'section'          => 'secondary',
				'item_type'        => 'link',
			),
			array(
				'db_id'            => 60010,
				'title'            => 'Partner with us',
				'url'              => '#',
				'menu_item_parent' => '60001',
				'section'          => 'secondary',
				'item_type'        => 'heading',
			),
			array(
				'db_id'            => 60011,
				'title'            => 'Affiliate program',
				'url'              => $B . '/affiliate-program/',
				'menu_item_parent' => '60010',
				'section'          => 'secondary',
				'item_type'        => 'link',
			),
			array(
				'db_id'            => 60012,
				'title'            => 'Ambassador program',
				'url'              => $B . '/handdn-ambassador/',
				'menu_item_parent' => '60010',
				'section'          => 'secondary',
				'item_type'        => 'link',
			),

			// 7. Client Services (Root Level 0 - Secondary)
			array(
				'db_id'            => 70001,
				'title'            => 'Client Services',
				'url'              => $B . '/support-center/how-to-measure-exactly-watch-strap/',
				'menu_item_parent' => '0',
				'section'          => 'secondary',
				'item_type'        => 'link',
				'visual'           => array(
					'enabled'    => true,
					'tex'        => 'smooth',
					'tone'       => '#22304a',
					'kicker'     => 'Client Services',
					'headline'   => 'Free worldwide shipping over $450. Returns within 30 days.',
					'cta_text'   => 'Contact us',
					'cta_url'    => $B . '/contact/',
				),
			),
			array(
				'db_id'            => 70002,
				'title'            => 'Size guide',
				'url'              => $B . '/support-center/how-to-measure-exactly-watch-strap/',
				'menu_item_parent' => '70001',
				'section'          => 'secondary',
				'item_type'        => 'link',
			),
			array(
				'db_id'            => 70003,
				'title'            => 'Track your order',
				'url'              => $B . '/my-account/track-order/',
				'menu_item_parent' => '70001',
				'section'          => 'secondary',
				'item_type'        => 'link',
			),
			array(
				'db_id'            => 70004,
				'title'            => 'FAQ',
				'url'              => $B . '/help-faqs/',
				'menu_item_parent' => '70001',
				'section'          => 'secondary',
				'item_type'        => 'link',
			),
			array(
				'db_id'            => 70005,
				'title'            => 'Shipping and delivery',
				'url'              => $B . '/shipping-delivery/',
				'menu_item_parent' => '70001',
				'section'          => 'secondary',
				'item_type'        => 'link',
			),
			array(
				'db_id'            => 70007,
				'title'            => 'Returns and refunds',
				'url'              => $B . '/return-refund/',
				'menu_item_parent' => '70001',
				'section'          => 'secondary',
				'item_type'        => 'link',
			),
			array(
				'db_id'            => 70006,
				'title'            => 'Contact us',
				'url'              => $B . '/contact/',
				'menu_item_parent' => '70001',
				'section'          => 'secondary',
				'item_type'        => 'link',
			),
		);
	}
}
