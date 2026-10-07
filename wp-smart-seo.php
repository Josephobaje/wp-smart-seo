<?php
/**
 * Plugin Name:       WP Smart SEO
 * Plugin URI:        https://github.com/Josephobaje/wp-smart-seo
 * Description:       Lightweight SEO essentials: custom meta titles and descriptions, Open Graph and Twitter Card tags, JSON-LD schema, an XML sitemap and canonical URLs.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Joseph Obaje
 * Author URI:        https://github.com/Josephobaje
 * License:           MIT
 * License URI:       https://opensource.org/licenses/MIT
 * Text Domain:       wp-smart-seo
 * Domain Path:       /languages
 *
 * @package WP_Smart_SEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WPSS_VERSION', '1.0.0' );
define( 'WPSS_FILE', __FILE__ );
define( 'WPSS_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPSS_URL', plugin_dir_url( __FILE__ ) );

require_once WPSS_DIR . 'includes/class-text.php';
require_once WPSS_DIR . 'includes/class-settings.php';
require_once WPSS_DIR . 'includes/class-post-meta.php';
require_once WPSS_DIR . 'includes/class-meta-resolver.php';
require_once WPSS_DIR . 'includes/class-schema.php';
require_once WPSS_DIR . 'includes/class-head-output.php';
require_once WPSS_DIR . 'includes/class-sitemap.php';
require_once WPSS_DIR . 'includes/class-meta-box.php';
require_once WPSS_DIR . 'includes/class-settings-page.php';
require_once WPSS_DIR . 'includes/class-plugin.php';

register_activation_hook( __FILE__, array( 'WP_Smart_SEO\Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'WP_Smart_SEO\Plugin', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'WP_Smart_SEO\Plugin', 'instance' ) );
