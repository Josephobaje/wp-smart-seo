<?php
/**
 * PHPUnit bootstrap: loads the plugin classes without WordPress.
 * WordPress functions are stubbed per test with Brain Monkey.
 */

declare(strict_types=1);

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/wordpress/' );
}
if ( ! defined( 'WPSS_FILE' ) ) {
	define( 'WPSS_FILE', dirname( __DIR__ ) . '/wp-smart-seo.php' );
}

require_once __DIR__ . '/stubs.php';

foreach ( array( 'text', 'settings', 'post-meta', 'meta-resolver', 'schema', 'head-output', 'sitemap', 'meta-box', 'settings-page' ) as $wpss_class ) {
	require_once dirname( __DIR__ ) . '/includes/class-' . $wpss_class . '.php';
}
