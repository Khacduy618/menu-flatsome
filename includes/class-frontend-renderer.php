<?php
/**
 * Class DMM_Frontend_Renderer
 *
 * Renders the customer-facing Handdn Drawer Menu HTML structure.
 *
 * @package Device_Menu_Manager
 */

defined( 'ABSPATH' ) || exit;

class DMM_Frontend_Renderer {

	/**
	 * Render the full drawer menu HTML into the page.
	 */
	public static function render(): void {
		$tree     = DMM_Menu_Switcher::get_active_menu_tree( 'desktop' );
		$settings = DMM_Menu_Data_Manager::get_drawer_settings();
		$tp_data  = DMM_Menu_Data_Manager::get_trustpilot_data();

		$panels_html = array();
		$row_index   = 0;

		// 1. Separate Root Level 0 items into Primary and Secondary.
		$primary_roots   = array();
		$secondary_roots = array();

		foreach ( $tree as $node ) {
			if ( ( $node['section'] ?? 'primary' ) === 'secondary' ) {
				$secondary_roots[] = $node;
			} else {
				$primary_roots[] = $node;
			}
		}

		// 2. Build Root Panel (#p-root, Level 0).
		$primary_html   = self::render_list_items( $primary_roots, $row_index, 0 );
		$secondary_html = self::render_list_items( $secondary_roots, $row_index, 0 );
		$promo_html     = self::render_root_promo( $settings, $row_index );
		$footer_html    = self::render_root_footer( $settings, $tp_data );

		// Mobile footer placed inside .hm-scroll to scroll together naturally.
		$footer_mobile_html  = ! empty( $footer_html ) ? str_replace( 'class="hm-foot"', 'class="hm-foot dmm-foot-mobile"', $footer_html ) : '';
		// Desktop footer pinned outside .hm-scroll at the bottom of the drawer.
		$footer_desktop_html = ! empty( $footer_html ) ? str_replace( 'class="hm-foot"', 'class="hm-foot dmm-foot-desktop"', $footer_html ) : '';

		$root_body = sprintf(
			'<ul class="hm-list primary">%1$s</ul><ul class="hm-list secondary">%2$s</ul>%3$s%4$s',
			$primary_html,
			$secondary_html,
			$promo_html,
			$footer_mobile_html
		);

		$panels_html[] = self::wrap_panel(
			'p-root',
			0,
			__( 'Menu', 'device-menu-manager' ),
			$root_body,
			$footer_desktop_html
		);

		// 3. Recursively build child panels (Level 1, 2, ...).
		foreach ( $tree as $root_node ) {
			if ( ( $root_node['item_type'] ?? 'link' ) === 'heading' ) {
				// If a root node is a heading, its children are displayed directly in Level 0 under this heading.
				// Any of those children that have their own children should have Level 1 panels!
				if ( ! empty( $root_node['children'] ) ) {
					foreach ( $root_node['children'] as $child ) {
						if ( ! empty( $child['children'] ) || ( ( $child['item_type'] ?? 'link' ) === 'sizes_grid' ) ) {
							self::build_child_panels( $child, 1, $panels_html );
						}
					}
				}
			} elseif ( ! empty( $root_node['children'] ) || ( ( $root_node['item_type'] ?? 'link' ) === 'sizes_grid' ) ) {
				self::build_child_panels( $root_node, 1, $panels_html );
			}
		}

		// 4. Output the complete markup.
		?>
		<!-- Handdn Scrim Overlay -->
		<div class="scrim" id="scrim" aria-hidden="true"></div>

		<!-- Handdn Main Drawer Menu -->
		<nav class="hm" id="hm" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Main menu', 'device-menu-manager' ); ?>">
			<?php echo implode( "\n", $panels_html ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</nav>

		<!-- Handdn Desktop Editorial Visual Banner -->
		<aside class="hm-visual" id="hmVis" aria-hidden="true">
			<a href="#" tabindex="-1">
				<span class="ph" data-layer="a"></span>
				<span class="ph out" data-layer="b"></span>
				<span class="cap">
					<span class="k"></span>
					<b></b>
					<span class="go"></span>
				</span>
			</a>
		</aside>

		<!-- Handdn Search Sheet Modal -->
		<?php self::render_search_modal(); ?>
		<?php
	}

	/**
	 * Recursively build child panels.
	 *
	 * @param array $parent_node Parent item node with children.
	 * @param int   $lvl Current level depth.
	 * @param array $panels_html Collector array of rendered panel strings.
	 */
	private static function build_child_panels( array $parent_node, int $lvl, array &$panels_html ): void {
		$node_id    = ! empty( $parent_node['id'] ) ? $parent_node['id'] : ( $parent_node['db_id'] ?? '' );
		$panel_id   = 'p-' . sanitize_html_class( (string) $node_id );
		$row_index  = 0;
		$body_parts = array();

		// Check if this node is a sizes grid.
		if ( ( $parent_node['item_type'] ?? 'link' ) === 'sizes_grid' ) {
			$body_parts[] = self::render_sizes_grid( $row_index, $parent_node );
		} elseif ( ! empty( $parent_node['children'] ) ) {
			// Group items under headings.
			$groups        = array();
			$current_group = array( 'title' => '', 'items' => array() );

			foreach ( $parent_node['children'] as $child ) {
				if ( ( $child['item_type'] ?? 'link' ) === 'heading' ) {
					if ( ! empty( $current_group['items'] ) || ! empty( $current_group['title'] ) ) {
						$groups[] = $current_group;
					}
					// If the heading itself has children (nested in tree builder):
					$heading_items = ! empty( $child['children'] ) ? $child['children'] : array();
					$current_group = array(
						'title' => $child['title'],
						'items' => $heading_items,
					);
				} else {
					$current_group['items'][] = $child;
				}
			}
			if ( ! empty( $current_group['items'] ) || ! empty( $current_group['title'] ) ) {
				$groups[] = $current_group;
			}

			foreach ( $groups as $group ) {
				$group_html = '<div class="hm-group">';
				if ( ! empty( $group['title'] ) ) {
					$group_html .= sprintf(
						'<h4 class="st" style="--i:%1$d">%2$s</h4>',
						$row_index++,
						esc_html( $group['title'] )
					);
				}
				$group_html .= '<ul class="hm-list">';
				$group_html .= self::render_list_items( $group['items'], $row_index, $lvl );
				$group_html .= '</ul></div>';
				$body_parts[] = $group_html;
			}
		}

		// Sub-panel Feature card (for mobile or bottom of panel).
		if ( ! empty( $parent_node['feature'] ) && ! empty( $parent_node['feature']['text'] ) ) {
			$f = $parent_node['feature'];
			$is_img = ! empty( $f['image_url'] );
			$media_attr = $is_img
				? sprintf( ' data-img="%s"', esc_url( $f['image_url'] ) )
				: sprintf( ' data-tex="%s" data-tone="%s"', esc_attr( $f['tex'] ?? ( $f['texture'] ?? 'smooth' ) ), esc_attr( $f['tone'] ?? '#6b3b20' ) );

			$body_parts[] = sprintf(
				'<a class="feature st" style="--i:%1$d" href="%2$s"><span class="ph zoom"%3$s></span><span class="ft"><span class="k">%4$s</span><p>%5$s</p><span class="go">%6$s</span></span></a>',
				$row_index++,
				esc_url( $f['cta_url'] ?? '#' ),
				$media_attr,
				esc_html( $f['kicker'] ?? '' ),
				esc_html( $f['text'] ?? '' ),
				esc_html( $f['cta_text'] ?? __( 'Discover', 'device-menu-manager' ) )
			);
		}

		$visual_attr = '';
		if ( ! empty( $parent_node['visual'] ) && ( ! empty( $parent_node['visual']['enabled'] ) || ! empty( $parent_node['visual']['headline'] ) || ! empty( $parent_node['visual']['kicker'] ) ) ) {
			$v = $parent_node['visual'];
			$visual_data = array(
				'tex'  => $v['tex'] ?? ( $v['texture'] ?? 'grain' ),
				'tone' => $v['tone'] ?? '#3b2416',
				'k'    => $v['kicker'] ?? '',
				't'    => $v['headline'] ?? '',
				'go'   => $v['cta_text'] ?? __( 'Shop', 'device-menu-manager' ),
				'href' => ! empty( $v['cta_url'] ) ? $v['cta_url'] : ( $parent_node['url'] ?? '#' ),
			);
			if ( ! empty( $v['image_url'] ) ) {
				$visual_data['img'] = $v['image_url'];
			}
			$visual_attr = sprintf( ' data-visual="%s"', esc_attr( wp_json_encode( $visual_data ) ) );
		}

		$panel_body = implode( '', $body_parts );
		$panels_html[] = self::wrap_panel(
			$panel_id,
			$lvl,
			$parent_node['title'],
			$panel_body,
			'',
			$visual_attr
		);

		// Recursively process child nodes that have their own children or are sizes_grid.
		if ( ! empty( $parent_node['children'] ) ) {
			foreach ( $parent_node['children'] as $child ) {
				if ( ( $child['item_type'] ?? 'link' ) === 'heading' && ! empty( $child['children'] ) ) {
					foreach ( $child['children'] as $grandchild ) {
						if ( ! empty( $grandchild['children'] ) || ( ( $grandchild['item_type'] ?? 'link' ) === 'sizes_grid' ) ) {
							self::build_child_panels( $grandchild, $lvl + 1, $panels_html );
						}
					}
				} elseif ( ! empty( $child['children'] ) || ( ( $child['item_type'] ?? 'link' ) === 'sizes_grid' ) ) {
					self::build_child_panels( $child, $lvl + 1, $panels_html );
				}
			}
		}
	}

	/**
	 * Get site logo URL for mobile drawer header.
	 *
	 * Checks Flatsome site_logo / site_logo_dark theme_mods, or WordPress custom_logo.
	 *
	 * @return string Logo image URL or empty string.
	 */
	public static function get_site_logo_url(): string {
		if ( ! function_exists( 'get_theme_mod' ) ) {
			return '';
		}

		// 1. Flatsome site_logo theme_mod.
		$logo = get_theme_mod( 'site_logo', '' );
		if ( ! empty( $logo ) && is_string( $logo ) ) {
			return $logo;
		}

		// 2. Flatsome site_logo_dark theme_mod.
		$logo_dark = get_theme_mod( 'site_logo_dark', '' );
		if ( ! empty( $logo_dark ) && is_string( $logo_dark ) ) {
			return $logo_dark;
		}

		// 3. WordPress core custom_logo.
		$custom_logo_id = get_theme_mod( 'custom_logo' );
		if ( ! empty( $custom_logo_id ) && function_exists( 'wp_get_attachment_image_src' ) ) {
			$image = wp_get_attachment_image_src( absint( $custom_logo_id ), 'full' );
			if ( ! empty( $image[0] ) ) {
				return $image[0];
			}
		}

		return '';
	}

	/**
	 * Wrap panel markup.
	 *
	 * @param string $id Panel ID attribute.
	 * @param int    $lvl Hierarchy depth.
	 * @param string $title Panel title.
	 * @param string $body Panel inner body.
	 * @param string $foot Optional panel footer markup.
	 * @return string
	 */
	private static function wrap_panel( string $id, int $lvl, string $title, string $body, string $foot = '', string $visual_attr = '' ): string {
		$icon_back  = '<svg viewBox="0 0 18 18" fill="none" stroke="currentColor" stroke-width="1.2" aria-hidden="true"><path d="M11 3.5 5.5 9l5.5 5.5"/></svg>';
		$icon_close = '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.2" aria-hidden="true"><path d="m4 4 12 12M16 4 4 16"/></svg>';

		$hidden_attr = ( 0 === $lvl ) ? ' hidden' : '';

		// Logo on mobile Level 0
		$logo_html = '';
		if ( 0 === $lvl ) {
			$logo_url  = self::get_site_logo_url();
			$site_name = function_exists( 'get_bloginfo' ) ? get_bloginfo( 'name' ) : 'HANDDN';
			if ( ! empty( $logo_url ) ) {
				$logo_inner = sprintf( '<img src="%1$s" alt="%2$s" class="hm-logo-img">', esc_url( $logo_url ), esc_attr( $site_name ) );
			} else {
				$logo_inner = sprintf( '<span class="hm-logo-text">%1$s</span>', esc_html( ! empty( $site_name ) ? $site_name : 'HANDDN' ) );
			}
			$logo_html = sprintf(
				'<a href="%1$s" class="hm-top-logo" aria-label="%2$s">%3$s</a>',
				esc_url( home_url( '/' ) ),
				esc_attr( $site_name ),
				$logo_inner
			);
		}

		return sprintf(
			'<section class="hm-panel lvl%1$d" id="%2$s" data-lvl="%1$d" style="--lvl:%1$d" aria-label="%3$s"%12$s>
				<div class="hm-top">
					%4$s
					<button class="hm-back" type="button" data-back>%5$s<span>%6$s</span></button>
					<span class="hm-title"%7$s>%3$s</span>
					<button class="hm-close" type="button" data-close><span>%8$s</span>%9$s</button>
				</div>
				<div class="hm-scroll">%10$s</div>%11$s
			</section>',
			$lvl,
			esc_attr( $id ),
			esc_attr( $title ),
			$logo_html,
			$icon_back,
			esc_html__( 'Back', 'device-menu-manager' ),
			$hidden_attr,
			esc_html__( 'Close', 'device-menu-manager' ),
			$icon_close,
			$body,
			$foot,
			$visual_attr
		);
	}

	/**
	 * Render list items for a panel.
	 *
	 * @param array $items Array of items.
	 * @param int   $row_index Reference to current stagger index.
	 * @param int   $lvl Current level depth.
	 * @return string
	 */
	private static function render_list_items( array $items, int &$row_index, int $lvl ): string {
		$icon_chev = '<svg class="chev" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.2" aria-hidden="true"><path d="m5 2.5 4.5 4.5L5 11.5"/></svg>';
		$html      = '';

		foreach ( $items as $item ) {
			if ( ( $item['item_type'] ?? 'link' ) === 'heading' ) {
				$i = $row_index++;
				$html .= sprintf(
					'<li class="st hm-heading-row" style="--i:%1$d"><h4 class="hm-heading-title">%2$s</h4></li>',
					$i,
					esc_html( $item['title'] )
				);
				if ( ! empty( $item['children'] ) ) {
					$html .= self::render_list_items( $item['children'], $row_index, $lvl );
				}
				continue;
			}

			$i       = $row_index++;
			$has_sub = ! empty( $item['children'] ) || ( ( $item['item_type'] ?? 'link' ) === 'sizes_grid' );
			$tag_elm = ! empty( $item['tag'] ) ? sprintf( '<span class="tag">%s</span>', esc_html( $item['tag'] ) ) : '';

			// Prepare visual json data attribute for hover interactions.
			$visual_json = '';
			if ( ! empty( $item['visual'] ) && ( ! empty( $item['visual']['enabled'] ) || ! empty( $item['visual']['headline'] ) || ! empty( $item['visual']['kicker'] ) ) ) {
				$v = $item['visual'];
				$visual_data = array(
					'tex'  => $v['tex'] ?? ( $v['texture'] ?? 'grain' ),
					'tone' => $v['tone'] ?? '#3b2416',
					'k'    => $v['kicker'] ?? '',
					't'    => $v['headline'] ?? '',
					'go'   => $v['cta_text'] ?? __( 'Shop', 'device-menu-manager' ),
					'href' => ! empty( $v['cta_url'] ) ? $v['cta_url'] : ( $item['url'] ?? '#' ),
				);
				if ( ! empty( $v['image_url'] ) ) {
					$visual_data['img'] = $v['image_url'];
				}
				$visual_json = sprintf( ' data-visual="%s"', esc_attr( wp_json_encode( $visual_data ) ) );
			}

			if ( $has_sub ) {
				$node_id   = ! empty( $item['id'] ) ? $item['id'] : ( $item['db_id'] ?? '' );
				$target_id = 'p-' . sanitize_html_class( (string) $node_id );
				$html .= sprintf(
					'<li class="st" style="--i:%1$d"><button class="hm-link" type="button" aria-expanded="false" aria-controls="%2$s" data-target="%2$s"%3$s><span class="lbl">%4$s</span>%5$s%6$s</button></li>',
					$i,
					esc_attr( $target_id ),
					$visual_json,
					esc_html( $item['title'] ),
					$tag_elm,
					$icon_chev
				);
			} else {
				$target_attr = ( ! empty( $item['target'] ) && '_blank' === $item['target'] ) ? ' target="_blank" rel="noopener"' : '';
				$html .= sprintf(
					'<li class="st" style="--i:%1$d"><a class="hm-link" href="%2$s"%3$s%4$s><span><span class="lbl">%5$s</span>%6$s</span></a></li>',
					$i,
					esc_url( $item['url'] ?? '#' ),
					$target_attr,
					$visual_json,
					esc_html( $item['title'] ),
					$tag_elm
				);
			}
		}

		return $html;
	}

	/**
	 * Render Level 0 Promo banner.
	 *
	 * @param array $settings Drawer settings.
	 * @param int   $row_index Reference to current row index.
	 * @return string
	 */
	private static function render_root_promo( array $settings, int &$row_index ): string {
		if ( empty( $settings['promo_enabled'] ) ) {
			return '';
		}

		$i = $row_index++;
		$is_promo_img = ! empty( $settings['promo_image_url'] );
		$media_attr = $is_promo_img
			? sprintf( ' data-img="%s"', esc_url( $settings['promo_image_url'] ) )
			: sprintf( ' data-tex="%s" data-tone="%s"', esc_attr( $settings['promo_texture'] ?? 'grain' ), esc_attr( $settings['promo_tone'] ?? '#8a4a26' ) );

		$strap_svg = ! empty( $settings['promo_show_strap'] )
			? '<svg class="strap" viewBox="0 0 320 56" fill="none" stroke="currentColor" stroke-width="1" aria-hidden="true"><rect x="2" y="16" width="134" height="24" rx="2"/><rect x="184" y="16" width="134" height="24" rx="12"/><rect x="138" y="10" width="44" height="36" rx="10"/><path stroke-dasharray="3 4" d="M10 22h118M10 34h118M192 22h110M192 34h110"/><circle cx="230" cy="28" r="1.8"/><circle cx="252" cy="28" r="1.8"/><circle cx="274" cy="28" r="1.8"/></svg>'
			: '';

		$kicker   = ! empty( $settings['promo_kicker'] ) ? $settings['promo_kicker'] : '3D Custom';
		$headline = ! empty( $settings['promo_headline'] ) ? $settings['promo_headline'] : 'Design your strap in 3D, then we cut and stitch it by hand.';
		$cta_text = ! empty( $settings['promo_cta_text'] ) ? $settings['promo_cta_text'] : 'Start designing';
		$cta_url  = ! empty( $settings['promo_cta_url'] ) ? $settings['promo_cta_url'] : 'https://handdn.us/product/3d-custom-watch-strap/';

		return sprintf(
			'<a class="hm-promo st" style="--i:%1$d" href="%2$s">
				<span class="ph zoom"%3$s></span>%4$s
				<span class="cap">
					<span class="k">%5$s</span>
					<b>%6$s</b>
					<span class="go">%7$s</span>
				</span>
			</a>',
			$i,
			esc_url( $cta_url ),
			$media_attr,
			$strap_svg,
			esc_html( $kicker ),
			esc_html( $headline ),
			esc_html( $cta_text )
		);
	}

	/**
	 * Render Level 0 Footer with Trustpilot and WooCommerce quick links.
	 *
	 * @param array $settings Drawer settings.
	 * @param array $tp_data Trustpilot rating data.
	 * @return string
	 */
	private static function render_root_footer( array $settings, array $tp_data ): string {
		$star_svg = '<svg viewBox="0 0 12 12" aria-hidden="true"><path fill="currentColor" d="M6 .8l1.6 3.4 3.7.4-2.8 2.5.8 3.7L6 8.9 2.7 10.8l.8-3.7L.7 4.6l3.7-.4z"/></svg>';
		$stars    = str_repeat( $star_svg, 5 );

		$tp_html = '';
		if ( ! empty( $tp_data['enabled'] ) && ! empty( $settings['trustpilot_enabled'] ) ) {
			$tp_html = sprintf(
				'<a class="tp" href="%1$s" rel="noopener" target="_blank" aria-label="%2$s">
					<span class="stars">%3$s</span>
					<span><strong>%4$s</strong> %5$s</span>
					<span class="u">%6$s %7$s</span>
				</a>',
				esc_url( $tp_data['url'] ),
				esc_attr( sprintf( 'Rated %s out of 5 from %s reviews on Trustpilot', $tp_data['rating'], number_format_i18n( $tp_data['count'] ) ) ),
				$stars,
				esc_html( $tp_data['rating'] ),
				esc_html__( 'Excellent', 'device-menu-manager' ),
				esc_html( number_format_i18n( $tp_data['count'] ) ),
				esc_html__( 'reviews on Trustpilot', 'device-menu-manager' )
			);
		}

		$links_html = '';

		// Account / Sign in Link
		if ( ! empty( $settings['account_link_enabled'] ) ) {
			$account_url = ! empty( $settings['wc_myaccount_url'] ) ? $settings['wc_myaccount_url'] : home_url( '/my-account/' );
			if ( function_exists( 'wc_get_page_permalink' ) ) {
				$wc_account_url = wc_get_page_permalink( 'myaccount' );
				if ( ! empty( $wc_account_url ) ) {
					$account_url = $wc_account_url;
				}
			}

			$is_logged_in  = function_exists( 'is_user_logged_in' ) ? is_user_logged_in() : false;
			$account_label = $is_logged_in ? esc_html__( 'My Account', 'device-menu-manager' ) : esc_html__( 'Sign in or register', 'device-menu-manager' );
			$account_label = apply_filters( 'dmm_account_footer_label', $account_label, $is_logged_in );
			$account_url   = apply_filters( 'dmm_account_footer_url', $account_url, $is_logged_in );

			$links_html .= sprintf( '<a href="%1$s">%2$s</a>', esc_url( $account_url ), $account_label );
		}

		// Track order Link
		if ( ! empty( $settings['track_order_enabled'] ) ) {
			$track_url   = ! empty( $settings['wc_track_url'] ) ? $settings['wc_track_url'] : home_url( '/my-account/track-order/' );
			$links_html .= sprintf( '<a href="%1$s">%2$s</a>', esc_url( $track_url ), esc_html__( 'Track order', 'device-menu-manager' ) );
		}

		// Contact Link
		if ( ! empty( $settings['contact_link_enabled'] ) ) {
			$contact_url = ! empty( $settings['wc_contact_url'] ) ? $settings['wc_contact_url'] : home_url( '/contact/' );
			$links_html .= sprintf( '<a href="%1$s">%2$s</a>', esc_url( $contact_url ), esc_html__( 'Contact', 'device-menu-manager' ) );
		}

		// Instagram Link
		$instagram_url = ! empty( $settings['wc_instagram_url'] ) ? $settings['wc_instagram_url'] : ( $settings['instagram_url'] ?? '' );
		if ( ! empty( $instagram_url ) ) {
			$links_html .= sprintf( '<a href="%1$s" rel="noopener" target="_blank">%2$s</a>', esc_url( $instagram_url ), esc_html__( 'Instagram', 'device-menu-manager' ) );
		}

		if ( empty( $tp_html ) && empty( $links_html ) ) {
			return '';
		}

		return sprintf( '<div class="hm-foot">%1$s%2$s</div>', $tp_html, $links_html );
	}

	/**
	 * Render Grid style sizes/options layout for child items.
	 *
	 * When an item has item_type: 'sizes_grid', its child items are rendered
	 * as an interactive multi-column grid of pill/box buttons, taking the name/title
	 * and URL of each child item.
	 *
	 * @param int   $row_index   Reference to current row index for CSS stagger animation.
	 * @param array $parent_node Parent item node containing child items and config.
	 * @return string HTML markup for the sizes grid group.
	 */
	private static function render_sizes_grid( int &$row_index, array $parent_node = array() ): string {
		$sizes_html = '';
		$children   = ! empty( $parent_node['children'] ) && is_array( $parent_node['children'] )
			? $parent_node['children']
			: array();

		if ( ! empty( $children ) ) {
			// Dynamic children: take name and link of each child menu item.
			foreach ( $children as $child ) {
				$title = trim( (string) ( $child['title'] ?? '' ) );
				if ( '' === $title ) {
					continue;
				}

				// If title matches a number + unit (e.g. "14mm", "14 mm", "1.5in", "20px"), wrap the unit in <small>.
				if ( preg_match( '/^(\d+(?:\.\d+)?)\s*([a-zA-Z"\'\.]+)$/', $title, $matches ) ) {
					$label_html = esc_html( $matches[1] ) . '<small>' . esc_html( $matches[2] ) . '</small>';
				} else {
					$label_html = esc_html( $title );
				}

				$url         = ! empty( $child['url'] ) ? $child['url'] : '#';
				$target_attr = ( ! empty( $child['target'] ) && '_blank' === $child['target'] ) ? ' target="_blank" rel="noopener"' : '';

				$sizes_html .= sprintf(
					'<li class="st" style="--i:%1$d"><a href="%2$s"%3$s aria-label="%4$s">%5$s</a></li>',
					$row_index++,
					esc_url( $url ),
					$target_attr,
					esc_attr( $title ),
					$label_html
				);
			}
		} else {
			// Fallback: standard 14mm to 26mm loop if no children have been added yet.
			$sizes = range( 14, 26 );
			foreach ( $sizes as $size ) {
				$sizes_html .= sprintf(
					'<li class="st" style="--i:%1$d"><a href="%2$s" aria-label="%3$s">%4$d<small>mm</small></a></li>',
					$row_index++,
					esc_url( home_url( '/attribute/lug-width/' . $size . 'mm/' ) ),
					esc_attr( sprintf( __( '%d millimeter straps', 'device-menu-manager' ), $size ) ),
					$size
				);
			}
		}

		$heading_title = ! empty( $parent_node['title'] )
			? esc_html( $parent_node['title'] )
			: esc_html__( 'Lug width', 'device-menu-manager' );

		$hint_url = ! empty( $parent_node['visual']['cta_url'] )
			? esc_url( $parent_node['visual']['cta_url'] )
			: esc_url( home_url( '/support-center/how-to-measure-exactly-watch-strap/' ) );

		$hint_cta = ! empty( $parent_node['visual']['cta_text'] )
			? esc_html( $parent_node['visual']['cta_text'] )
			: esc_html__( 'Measure your lugs', 'device-menu-manager' );

		return sprintf(
			'<div class="hm-group">
				<h4 class="st" style="--i:0">%1$s</h4>
				<ul class="sizes">%2$s</ul>
				<p class="hint st" style="--i:%3$d">%4$s <a href="%5$s">%6$s</a> %7$s</p>
			</div>',
			$heading_title,
			$sizes_html,
			$row_index++,
			esc_html__( 'Not sure?', 'device-menu-manager' ),
			$hint_url,
			$hint_cta,
			esc_html__( 'in two minutes.', 'device-menu-manager' )
		);
	}

	/**
	 * Render Search Sheet Modal.
	 */
	private static function render_search_modal(): void {
		$icon_close = '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.2" aria-hidden="true"><path d="m4 4 12 12M16 4 4 16"/></svg>';
		$icon_glass = '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.2" aria-hidden="true"><circle cx="8.5" cy="8.5" r="5.5"/><path d="m13 13 5 5"/></svg>';
		?>
		<div class="srch" id="srch" role="dialog" aria-modal="true" aria-label="<?php echo esc_attr( __( 'Search', 'device-menu-manager' ) ); ?>">
			<div class="srch-in">
				<div class="srch-row">
					<button class="hm-close" id="srchClose" type="button">
						<span><?php echo esc_html( __( 'Close', 'device-menu-manager' ) ); ?></span>
						<?php echo $icon_close; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</button>
				</div>
				<form action="<?php echo esc_url( home_url( '/' ) ); ?>" method="get" role="search">
					<?php echo $icon_glass; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<label for="dmm-q" class="vh"><?php echo esc_html( __( 'Search straps', 'device-menu-manager' ) ); ?></label>
					<input id="dmm-q" name="s" type="search" placeholder="<?php echo esc_attr( __( 'Search leathers, styles, sizes', 'device-menu-manager' ) ); ?>" autocomplete="off">
					<input type="hidden" name="post_type" value="product">
				</form>
				<div>
					<div class="eyebrow" style="margin-bottom:12px"><?php echo esc_html( __( 'Popular', 'device-menu-manager' ) ); ?></div>
					<div class="sugg" id="popular">
						<a href="<?php echo esc_url( home_url( '/product/3d-custom-watch-strap/' ) ); ?>"><?php echo esc_html( __( '3D Custom', 'device-menu-manager' ) ); ?></a>
						<a href="<?php echo esc_url( home_url( '/shop/available-straps/' ) ); ?>"><?php echo esc_html( __( 'In stock', 'device-menu-manager' ) ); ?></a>
						<a href="<?php echo esc_url( home_url( '/attribute/leather/alligator/' ) ); ?>"><?php echo esc_html( __( 'Alligator', 'device-menu-manager' ) ); ?></a>
						<a href="<?php echo esc_url( home_url( '/shop/apple-watch-straps/' ) ); ?>"><?php echo esc_html( __( 'Apple Watch', 'device-menu-manager' ) ); ?></a>
						<a href="<?php echo esc_url( home_url( '/attribute/lug-width/20mm/' ) ); ?>"><?php echo esc_html( __( '20mm', 'device-menu-manager' ) ); ?></a>
						<a href="<?php echo esc_url( home_url( '/support-center/how-to-measure-exactly-watch-strap/' ) ); ?>"><?php echo esc_html( __( 'Size guide', 'device-menu-manager' ) ); ?></a>
					</div>
				</div>
			</div>
		</div>
		<?php
	}
}
