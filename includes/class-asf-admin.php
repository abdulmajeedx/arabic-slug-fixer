<?php
/**
 * Admin screen: settings + bulk converter for existing Arabic slugs.
 *
 * @package ArabicSlugFixer
 */

defined( 'ABSPATH' ) || exit;

final class ASF_Admin {

	private const BATCH = 50;
	private const PAGE  = 'arabic-slug-fixer';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_post_asf_convert', array( $this, 'handle_convert' ) );
	}

	public function menu(): void {
		add_management_page(
			__( 'Arabic Slug Fixer', 'arabic-slug-fixer' ),
			__( 'Arabic Slugs', 'arabic-slug-fixer' ),
			'manage_options',
			self::PAGE,
			array( $this, 'render' )
		);
	}

	public function register_settings(): void {
		register_setting(
			'asf',
			ASF_OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => static function ( $in ) {
					$in = is_array( $in ) ? $in : array();
					return array(
						'enabled'    => empty( $in['enabled'] ) ? 0 : 1,
						'stop_words' => empty( $in['stop_words'] ) ? 0 : 1,
						'max_words'  => max( 0, min( 20, absint( $in['max_words'] ?? 8 ) ) ),
					);
				},
			)
		);
	}

	/** IDs of posts whose stored slug is percent-encoded Arabic (UTF-8 lead bytes D8–DB). */
	private function arabic_slug_ids( int $limit = 0 ): array {
		global $wpdb;
		$types    = array_values( get_post_types( array( 'public' => true ) ) );
		$statuses = array( 'publish', 'draft', 'pending', 'private', 'future', 'inherit' );

		$like = array();
		foreach ( array( 'd8', 'd9', 'da', 'db' ) as $b ) {
			$like[] = $wpdb->prepare( 'post_name LIKE %s', '%' . $wpdb->esc_like( '%' . $b . '%' ) . '%' );
		}
		$in_types    = implode( ',', array_fill( 0, count( $types ), '%s' ) );
		$in_statuses = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql = $wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts} WHERE post_type IN ($in_types) AND post_status IN ($in_statuses) AND (" . implode( ' OR ', $like ) . ') ORDER BY ID ASC',
			array_merge( $types, $statuses )
		);
		if ( $limit > 0 ) {
			$sql .= ' LIMIT ' . (int) $limit;
		}
		return array_map( 'intval', $wpdb->get_col( $sql ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	private function new_slug( WP_Post $post ): string {
		return sanitize_title( rawurldecode( $post->post_name ) );
	}

	public function handle_convert(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'arabic-slug-fixer' ) );
		}
		check_admin_referer( 'asf_convert' );

		$done = 0;
		foreach ( $this->arabic_slug_ids( self::BATCH ) as $id ) {
			$post = get_post( $id );
			$slug = $post ? $this->new_slug( $post ) : '';
			if ( '' === $slug || $slug === $post->post_name ) {
				continue;
			}
			// Keep the old slug for our 404 → 301 fallback (covers pages; core only covers posts).
			add_post_meta( $id, '_asf_old_slug', $post->post_name );
			$res = wp_update_post( array( 'ID' => $id, 'post_name' => $slug ), true );
			if ( ! is_wp_error( $res ) ) {
				++$done;
			}
		}

		wp_safe_redirect( add_query_arg( array( 'page' => self::PAGE, 'asf_done' => $done ), admin_url( 'tools.php' ) ) );
		exit;
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$s       = asf_settings();
		$all     = $this->arabic_slug_ids();
		$preview = array_slice( $all, 0, self::BATCH );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Arabic Slug Fixer', 'arabic-slug-fixer' ); ?></h1>

			<?php if ( isset( $_GET['asf_done'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<div class="notice notice-success"><p>
					<?php
					/* translators: %d: number of converted items */
					printf( esc_html__( 'Converted %d slugs. Old links redirect with 301.', 'arabic-slug-fixer' ), absint( $_GET['asf_done'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
					?>
				</p></div>
			<?php endif; ?>

			<h2><?php esc_html_e( 'Settings', 'arabic-slug-fixer' ); ?></h2>
			<form method="post" action="options.php">
				<?php settings_fields( 'asf' ); ?>
				<table class="form-table" role="presentation">
					<tr><th><?php esc_html_e( 'Auto-convert new slugs', 'arabic-slug-fixer' ); ?></th>
						<td><input type="checkbox" name="<?php echo esc_attr( ASF_OPTION ); ?>[enabled]" value="1" <?php checked( $s['enabled'] ); ?>></td></tr>
					<tr><th><?php esc_html_e( 'Remove Arabic stop words', 'arabic-slug-fixer' ); ?></th>
						<td><input type="checkbox" name="<?php echo esc_attr( ASF_OPTION ); ?>[stop_words]" value="1" <?php checked( $s['stop_words'] ); ?>>
						<p class="description">في، من، على، إلى، عن …</p></td></tr>
					<tr><th><?php esc_html_e( 'Max words in slug (0 = no limit)', 'arabic-slug-fixer' ); ?></th>
						<td><input type="number" min="0" max="20" name="<?php echo esc_attr( ASF_OPTION ); ?>[max_words]" value="<?php echo esc_attr( $s['max_words'] ); ?>"></td></tr>
				</table>
				<?php submit_button(); ?>
			</form>

			<hr>
			<h2><?php esc_html_e( 'Convert existing Arabic slugs', 'arabic-slug-fixer' ); ?></h2>
			<?php if ( ! $all ) : ?>
				<p><?php esc_html_e( 'No Arabic slugs found.', 'arabic-slug-fixer' ); ?></p>
			<?php else : ?>
				<p><strong>
					<?php
					/* translators: %d: number of items */
					printf( esc_html__( '%d items have Arabic slugs.', 'arabic-slug-fixer' ), count( $all ) );
					?>
				</strong> <?php esc_html_e( 'Back up your database first. Each run converts up to 50 items.', 'arabic-slug-fixer' ); ?></p>
				<table class="widefat striped" style="max-width:900px">
					<thead><tr><th><?php esc_html_e( 'Title', 'arabic-slug-fixer' ); ?></th><th><?php esc_html_e( 'Current slug', 'arabic-slug-fixer' ); ?></th><th><?php esc_html_e( 'New slug', 'arabic-slug-fixer' ); ?></th></tr></thead>
					<tbody>
					<?php
					foreach ( $preview as $id ) :
						$p = get_post( $id );
						?>
						<tr>
							<td><?php echo esc_html( get_the_title( $p ) ); ?></td>
							<td dir="rtl"><?php echo esc_html( rawurldecode( $p->post_name ) ); ?></td>
							<td><code><?php echo esc_html( $this->new_slug( $p ) ); ?></code></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="asf_convert">
					<?php wp_nonce_field( 'asf_convert' ); ?>
					<?php submit_button( __( 'Convert next 50', 'arabic-slug-fixer' ), 'primary', 'submit', true, array( 'onclick' => "return confirm('" . esc_js( __( 'Convert these slugs? Make sure you have a backup.', 'arabic-slug-fixer' ) ) . "');" ) ); ?>
				</form>
			<?php endif; ?>
		</div>
		<?php
	}
}
