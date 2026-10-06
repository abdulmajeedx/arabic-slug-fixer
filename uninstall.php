<?php
/**
 * Remove plugin data on uninstall. Converted slugs are kept on purpose.
 *
 * @package ArabicSlugFixer
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'asf_settings' );
