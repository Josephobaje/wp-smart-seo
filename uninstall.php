<?php
/**
 * Removes all plugin data when WP Smart SEO is deleted from the Plugins screen.
 *
 * @package WP_Smart_SEO
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'wpss_settings' );

foreach ( array( '_wpss_title', '_wpss_description', '_wpss_canonical', '_wpss_noindex' ) as $wpss_meta_key ) {
	delete_post_meta_by_key( $wpss_meta_key );
}
