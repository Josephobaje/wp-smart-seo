<?php
/**
 * XML sitemap with an index and one paginated sitemap per post type.
 *
 * URLs: /smart-sitemap.xml (index) and /smart-sitemap-{post_type}-{page}.xml.
 *
 * @package WP_Smart_SEO
 */

namespace WP_Smart_SEO;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Generates and serves the XML sitemap.
 */
class Sitemap {

	const QUERY_VAR      = 'wpss_sitemap';
	const PAGE_QUERY_VAR = 'wpss_sitemap_page';
	const PER_PAGE       = 1000;
	const XMLNS          = 'http://www.sitemaps.org/schemas/sitemap/0.9';

	/**
	 * Settings instance.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param Settings $settings Plugin settings.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Hooks the sitemap into WordPress.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'init', array( __CLASS__, 'add_rewrite_rules' ) );
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_action( 'template_redirect', array( $this, 'maybe_render' ), 0 );
		add_filter( 'robots_txt', array( $this, 'robots_txt' ), 10, 2 );
		if ( $this->settings->get( 'disable_core_sitemap' ) ) {
			add_filter( 'wp_sitemaps_enabled', '__return_false' );
		}
	}

	/**
	 * Registers the rewrite rules.
	 *
	 * @return void
	 */
	public static function add_rewrite_rules() {
		add_rewrite_rule( '^smart-sitemap\.xml$', 'index.php?' . self::QUERY_VAR . '=index', 'top' );
		add_rewrite_rule(
			'^smart-sitemap-([a-z0-9_\-]+)-([0-9]+)\.xml$',
			'index.php?' . self::QUERY_VAR . '=$matches[1]&' . self::PAGE_QUERY_VAR . '=$matches[2]',
			'top'
		);
	}

	/**
	 * Registers the public query vars.
	 *
	 * @param string[] $vars Query vars.
	 * @return string[]
	 */
	public static function query_vars( $vars ) {
		$vars[] = self::QUERY_VAR;
		$vars[] = self::PAGE_QUERY_VAR;
		return $vars;
	}

	/**
	 * Public URL of the sitemap index.
	 *
	 * @return string
	 */
	public static function index_url() {
		return home_url( '/smart-sitemap.xml' );
	}

	/**
	 * Appends the sitemap location to the virtual robots.txt.
	 *
	 * @param string $output Robots.txt content.
	 * @param bool   $is_public Whether the site is visible to search engines.
	 * @return string
	 */
	public function robots_txt( $output, $is_public ) {
		if ( $is_public && $this->settings->get( 'sitemap_enabled' ) ) {
			$output = rtrim( (string) $output ) . "\n\nSitemap: " . esc_url_raw( self::index_url() ) . "\n";
		}
		return $output;
	}

	/**
	 * Serves the sitemap when one of our URLs is requested.
	 *
	 * @return void
	 */
	public function maybe_render() {
		$type = sanitize_key( (string) get_query_var( self::QUERY_VAR ) );
		if ( '' === $type ) {
			return;
		}
		if ( ! $this->settings->get( 'sitemap_enabled' ) ) {
			return;
		}

		$enabled = (array) $this->settings->get( 'sitemap_post_types' );
		if ( 'index' === $type ) {
			$xml = self::render_index( $this->index_entries( $enabled ) );
		} elseif ( in_array( $type, $enabled, true ) ) {
			$page = max( 1, absint( get_query_var( self::PAGE_QUERY_VAR ) ) );
			$urls = $this->post_type_urls( $type, $page );
			if ( empty( $urls ) && $page > 1 ) {
				return;
			}
			$xml = self::render_urlset( $urls );
		} else {
			return;
		}

		status_header( 200 );
		header( 'Content-Type: application/xml; charset=UTF-8' );
		header( 'X-Robots-Tag: noindex, follow' );
		echo $xml; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Every value is escaped by self::xml() while building the document.
		exit;
	}

	/**
	 * Index entries (one sitemap per post type page).
	 *
	 * @param string[] $post_types Enabled post types.
	 * @return array<int, array<string,string>>
	 */
	private function index_entries( array $post_types ) {
		$entries = array();
		foreach ( $post_types as $post_type ) {
			if ( ! post_type_exists( $post_type ) ) {
				continue;
			}
			$counts    = wp_count_posts( $post_type );
			$published = isset( $counts->publish ) ? (int) $counts->publish : 0;
			$pages     = self::page_count( $published );
			for ( $page = 1; $page <= $pages; $page++ ) {
				$entries[] = array(
					'loc'     => home_url( sprintf( '/smart-sitemap-%s-%d.xml', $post_type, $page ) ),
					'lastmod' => (string) get_lastpostmodified( 'gmt', $post_type ),
				);
			}
		}
		return $entries;
	}

	/**
	 * URLs for one page of a post type, excluding noindexed and password-protected posts.
	 *
	 * @param string $post_type Post type.
	 * @param int    $page      1-based page.
	 * @return array<int, array<string,string>>
	 */
	private function post_type_urls( $post_type, $page ) {
		$posts = get_posts(
			array(
				'post_type'        => $post_type,
				'post_status'      => 'publish',
				'has_password'     => false,
				'posts_per_page'   => self::PER_PAGE,
				'paged'            => $page,
				'orderby'          => 'modified',
				'order'            => 'DESC',
				'suppress_filters' => false,
			)
		);
		$urls  = array();
		if ( 1 === $page && 'page' === $post_type && 'posts' === get_option( 'show_on_front' ) ) {
			$urls[] = array(
				'loc'     => home_url( '/' ),
				'lastmod' => (string) get_lastpostmodified( 'gmt' ),
			);
		}
		foreach ( $posts as $post ) {
			if ( Post_Meta::is_noindex( $post->ID ) ) {
				continue;
			}
			$canonical = Post_Meta::get( $post->ID, Post_Meta::CANONICAL );
			$permalink = (string) get_permalink( $post );
			if ( '' !== $canonical && untrailingslashit( $canonical ) !== untrailingslashit( $permalink ) ) {
				continue; // Canonicalised elsewhere: do not list duplicates.
			}
			$urls[] = array(
				'loc'     => $permalink,
				'lastmod' => (string) get_post_modified_time( 'c', true, $post ),
			);
		}
		return $urls;
	}

	/**
	 * Number of sitemap pages needed for $total URLs.
	 *
	 * @param int $total Number of URLs.
	 * @return int
	 */
	public static function page_count( $total ) {
		return (int) max( 1, (int) ceil( max( 0, (int) $total ) / self::PER_PAGE ) );
	}

	/**
	 * Renders a sitemap index document.
	 *
	 * @param array<int, array<string,string>> $entries Items with loc and optional lastmod.
	 * @return string
	 */
	public static function render_index( array $entries ) {
		return self::document( 'sitemapindex', 'sitemap', $entries );
	}

	/**
	 * Renders a urlset document.
	 *
	 * @param array<int, array<string,string>> $urls Items with loc and optional lastmod.
	 * @return string
	 */
	public static function render_urlset( array $urls ) {
		return self::document( 'urlset', 'url', $urls );
	}

	/**
	 * Shared XML builder.
	 *
	 * @param string                           $root  Root element name.
	 * @param string                           $item  Item element name.
	 * @param array<int, array<string,string>> $items Items with loc and optional lastmod.
	 * @return string
	 */
	private static function document( $root, $item, array $items ) {
		$xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$xml .= '<' . $root . ' xmlns="' . self::XMLNS . '">' . "\n";
		foreach ( $items as $entry ) {
			if ( empty( $entry['loc'] ) ) {
				continue;
			}
			$xml    .= "\t<" . $item . ">\n\t\t<loc>" . self::xml( $entry['loc'] ) . "</loc>\n";
			$lastmod = empty( $entry['lastmod'] ) ? '' : self::w3c_date( $entry['lastmod'] );
			if ( '' !== $lastmod ) {
				$xml .= "\t\t<lastmod>" . self::xml( $lastmod ) . "</lastmod>\n";
			}
			$xml .= "\t</" . $item . ">\n";
		}
		return $xml . '</' . $root . '>' . "\n";
	}

	/**
	 * Escapes text for XML element content.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	public static function xml( $value ) {
		return htmlspecialchars( (string) $value, ENT_QUOTES | ENT_XML1, 'UTF-8' );
	}

	/**
	 * Normalises a date string to W3C datetime (e.g. 2026-10-07T08:00:00+00:00).
	 *
	 * @param string $date Date in any strtotime()-compatible format.
	 * @return string Empty string for invalid dates.
	 */
	public static function w3c_date( $date ) {
		$timestamp = strtotime( (string) $date );
		return false === $timestamp ? '' : gmdate( 'Y-m-d\TH:i:s+00:00', $timestamp );
	}
}
