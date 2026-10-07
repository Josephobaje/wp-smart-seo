<?php
/**
 * Plugin bootstrap: creates the services and wires the hooks.
 *
 * @package WP_Smart_SEO
 */

namespace WP_Smart_SEO;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main plugin class (single instance).
 */
final class Plugin {

	/**
	 * The single instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Settings instance.
	 *
	 * @var Settings
	 */
	public $settings;

	/**
	 * Resolver instance.
	 *
	 * @var Meta_Resolver
	 */
	public $resolver;

	/**
	 * Returns (and on first call boots) the plugin.
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->boot();
		}
		return self::$instance;
	}

	/**
	 * Creates services and registers hooks.
	 *
	 * @return void
	 */
	private function boot() {
		$this->settings = new Settings();
		$this->resolver = new Meta_Resolver( $this->settings );

		add_action( 'init', array( __CLASS__, 'load_textdomain' ) );
		add_action( 'init', array( Post_Meta::class, 'register' ), 20 );

		( new Head_Output( $this->settings, $this->resolver ) )->register_hooks();
		( new Sitemap( $this->settings ) )->register_hooks();

		if ( is_admin() ) {
			( new Meta_Box( $this->resolver ) )->register_hooks();
			( new Settings_Page( $this->settings ) )->register_hooks();
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
		}
	}

	/**
	 * Loads translations from /languages.
	 *
	 * @return void
	 */
	public static function load_textdomain() {
		load_plugin_textdomain( 'wp-smart-seo', false, dirname( plugin_basename( WPSS_FILE ) ) . '/languages' );
	}

	/**
	 * Enqueues the admin CSS/JS on the post editor and the settings page only.
	 *
	 * @param string $hook_suffix Current admin page.
	 * @return void
	 */
	public static function enqueue_admin_assets( $hook_suffix ) {
		if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php', 'settings_page_' . Settings_Page::SLUG ), true ) ) {
			return;
		}
		wp_enqueue_style( 'wpss-admin', WPSS_URL . 'assets/css/admin.css', array(), WPSS_VERSION );
		wp_enqueue_script( 'wpss-admin', WPSS_URL . 'assets/js/admin.js', array(), WPSS_VERSION, true );
		wp_set_script_translations( 'wpss-admin', 'wp-smart-seo', WPSS_DIR . 'languages' );
	}

	/**
	 * Activation: register rewrite rules and flush them once.
	 *
	 * @return void
	 */
	public static function activate() {
		Sitemap::add_rewrite_rules();
		flush_rewrite_rules( false );
	}

	/**
	 * Deactivation: remove our rewrite rules.
	 *
	 * @return void
	 */
	public static function deactivate() {
		flush_rewrite_rules( false );
	}
}
