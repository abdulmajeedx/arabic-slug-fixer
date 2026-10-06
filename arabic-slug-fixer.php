<?php
/**
 * Plugin Name:       Arabic Slug Fixer
 * Plugin URI:        https://github.com/abdulmajeedx/arabic-slug-fixer
 * Description:       Converts Arabic post, page, product and term slugs into short, readable Latin URLs, with a safe bulk converter that keeps 301 redirects for old links.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Abdulmajeed
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       arabic-slug-fixer
 * Domain Path:       /languages
 *
 * @package ArabicSlugFixer
 */

defined( 'ABSPATH' ) || exit;

define( 'ASF_VERSION', '1.0.0' );
define( 'ASF_OPTION', 'asf_settings' );

require_once __DIR__ . '/includes/class-asf-transliterator.php';

/** Plugin settings with defaults. */
function asf_settings(): array {
	return wp_parse_args(
		get_option( ASF_OPTION, array() ),
		array(
			'enabled'    => 1,
			'stop_words' => 1,
			'max_words'  => 8,
		)
	);
}

/**
 * Transliterate Arabic before core's sanitize_title_with_dashes (priority 10).
 * Only runs in the 'save' context, so existing slugs are never touched.
 */
add_filter(
	'sanitize_title',
	static function ( $title, $raw_title = '', $context = 'display' ) {
		if ( 'save' !== $context || ! is_string( $title ) ) {
			return $title;
		}
		$s = asf_settings();
		if ( empty( $s['enabled'] ) || ! ASF_Transliterator::has_arabic( $title ) ) {
			return $title;
		}
		return ASF_Transliterator::transliterate( $title, ! empty( $s['stop_words'] ), (int) $s['max_words'] );
	},
	9,
	3
);

/**
 * 404 fallback: if the last URL segment matches a slug we converted, 301 to the new URL.
 * Core's wp_old_slug_redirect only covers published non-hierarchical posts; this also covers pages.
 */
add_action(
	'template_redirect',
	static function () {
		if ( ! is_404() || empty( $_SERVER['REQUEST_URI'] ) ) {
			return;
		}
		$path = (string) wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$seg  = basename( untrailingslashit( $path ) );
		if ( '' === $seg ) {
			return;
		}
		$old = strtolower( rawurlencode( rawurldecode( $seg ) ) );
		$ids = get_posts(
			array(
				'post_type'      => 'any',
				'post_status'    => 'publish',
				'meta_key'       => '_asf_old_slug', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => $old, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'fields'         => 'ids',
				'posts_per_page' => 1,
				'no_found_rows'  => true,
			)
		);
		if ( $ids ) {
			wp_safe_redirect( get_permalink( $ids[0] ), 301 );
			exit;
		}
	},
	11
);

add_action(
	'init',
	static function () {
		load_plugin_textdomain( 'arabic-slug-fixer', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	}
);

if ( is_admin() ) {
	require_once __DIR__ . '/includes/class-asf-admin.php';
	new ASF_Admin();
}

add_filter(
	'plugin_action_links_' . plugin_basename( __FILE__ ),
	static function ( array $links ): array {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'tools.php?page=arabic-slug-fixer' ) ) . '">' . esc_html__( 'Settings', 'arabic-slug-fixer' ) . '</a>' );
		return $links;
	}
);
