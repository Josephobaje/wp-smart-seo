<?php
/**
 * Works out the SEO title, description, canonical URL and robots directives
 * for the current request.
 *
 * @package WP_Smart_SEO
 */

namespace WP_Smart_SEO;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolves SEO values from post meta, settings and sensible fallbacks.
 */
class Meta_Resolver {

	/**
	 * Recommended maximum description length.
	 */
	const DESCRIPTION_LENGTH = 160;

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
	 * Replaces template variables and tidies the result.
	 *
	 * Supported variables: %title%, %sitename%, %tagline%, %sep%, %page%.
	 * Separators left dangling by empty variables are removed, so
	 * "%sitename% %sep% %tagline%" with no tagline becomes just the site name.
	 *
	 * @param string               $template Title template.
	 * @param array<string,string> $vars     Variable values keyed without percent signs.
	 * @return string
	 */
	public static function render_template( $template, array $vars ) {
		$sep         = isset( $vars['sep'] ) ? (string) $vars['sep'] : '–';
		$replace     = array();
		$placeholder = "\x1F";
		foreach ( array( 'title', 'sitename', 'tagline', 'page' ) as $name ) {
			$replace[ '%' . $name . '%' ] = isset( $vars[ $name ] ) ? Text::squish( (string) $vars[ $name ] ) : '';
		}
		$replace['%sep%'] = $placeholder;

		$output = strtr( (string) $template, $replace );
		$output = (string) preg_replace( '/%[a-z_]+%/', '', $output );
		$output = Text::squish( $output );

		// Collapse repeated separators and trim leading/trailing ones.
		$quoted = preg_quote( $placeholder, '/' );
		$output = (string) preg_replace( '/(' . $quoted . '\s*)+/', $placeholder . ' ', $output );
		$output = trim( $output, " \t" . $placeholder );
		$output = str_replace( $placeholder, $sep, $output );
		return Text::squish( $output );
	}

	/**
	 * Common template variables.
	 *
	 * @param string $title Title of the current item.
	 * @return array<string,string>
	 */
	private function vars( $title ) {
		$paged = (int) max( 1, (int) get_query_var( 'paged' ), (int) get_query_var( 'page' ) );
		return array(
			'title'    => (string) $title,
			'sitename' => (string) get_bloginfo( 'name' ),
			'tagline'  => (string) get_bloginfo( 'description' ),
			'sep'      => (string) $this->settings->get( 'separator' ),
			/* translators: %d: page number. */
			'page'     => $paged > 1 ? sprintf( __( 'Page %d', 'wp-smart-seo' ), $paged ) : '',
		);
	}

	/**
	 * SEO title for a post: the custom title if set, otherwise the template.
	 *
	 * @param \WP_Post $post Post object.
	 * @return string
	 */
	public function post_title( $post ) {
		$custom = Post_Meta::get( $post->ID, Post_Meta::TITLE );
		if ( '' !== $custom ) {
			return $custom;
		}
		return self::render_template( (string) $this->settings->get( 'title_template' ), $this->vars( get_the_title( $post ) ) );
	}

	/**
	 * Title for the front page / blog home.
	 *
	 * @return string
	 */
	public function home_title() {
		return self::render_template( (string) $this->settings->get( 'home_title_template' ), $this->vars( '' ) );
	}

	/**
	 * Meta description for a post: custom, then excerpt, then trimmed content.
	 *
	 * @param \WP_Post $post Post object.
	 * @return string
	 */
	public function post_description( $post ) {
		$custom = Post_Meta::get( $post->ID, Post_Meta::DESCRIPTION );
		if ( '' !== $custom ) {
			return $custom;
		}
		$source = '' !== trim( (string) $post->post_excerpt ) ? $post->post_excerpt : $post->post_content;
		return Text::truncate( Text::clean( (string) $source ), self::DESCRIPTION_LENGTH );
	}

	/**
	 * Description for the home page.
	 *
	 * @return string
	 */
	public function home_description() {
		$description = (string) $this->settings->get( 'home_description' );
		if ( '' === $description ) {
			$description = (string) get_bloginfo( 'description' );
		}
		return Text::truncate( $description, self::DESCRIPTION_LENGTH );
	}

	/**
	 * Canonical URL for a post: the override if set, otherwise the permalink
	 * (including the page number for paginated posts).
	 *
	 * @param \WP_Post $post Post object.
	 * @return string
	 */
	public function post_canonical( $post ) {
		$custom = Post_Meta::get( $post->ID, Post_Meta::CANONICAL );
		if ( '' !== $custom ) {
			return $custom;
		}
		$url  = (string) get_permalink( $post );
		$page = (int) get_query_var( 'page' );
		if ( $page > 1 && '' !== $url ) {
			$url = trailingslashit( $url ) . user_trailingslashit( (string) $page, 'single_paged' );
		}
		return $url;
	}

	/**
	 * Whether the current request should carry a noindex directive.
	 *
	 * @param \WP_Post|null $post Queried post on singular views.
	 * @return bool
	 */
	public function should_noindex( $post = null ) {
		if ( $post && Post_Meta::is_noindex( $post->ID ) ) {
			return true;
		}
		if ( is_search() && $this->settings->get( 'noindex_search' ) ) {
			return true;
		}
		if ( is_date() && $this->settings->get( 'noindex_date_archives' ) ) {
			return true;
		}
		return is_404();
	}

	/**
	 * Builds the Open Graph and Twitter Card tags as attribute arrays.
	 *
	 * Each item is `array( 'property' => ..., 'content' => ... )` or
	 * `array( 'name' => ..., 'content' => ... )`. Empty values are skipped.
	 *
	 * @param array<string,string> $data Keys: type, title, description, url, site_name,
	 *                                   image, locale, published, modified, twitter_site.
	 * @return array<int, array<string,string>>
	 */
	public static function social_tags( array $data ) {
		$get  = static function ( $key ) use ( $data ) {
			return isset( $data[ $key ] ) ? trim( (string) $data[ $key ] ) : '';
		};
		$tags = array(
			array( 'property', 'og:locale', $get( 'locale' ) ),
			array( 'property', 'og:type', '' !== $get( 'type' ) ? $get( 'type' ) : 'website' ),
			array( 'property', 'og:title', $get( 'title' ) ),
			array( 'property', 'og:description', $get( 'description' ) ),
			array( 'property', 'og:url', $get( 'url' ) ),
			array( 'property', 'og:site_name', $get( 'site_name' ) ),
			array( 'property', 'og:image', $get( 'image' ) ),
		);
		if ( 'article' === $get( 'type' ) ) {
			$tags[] = array( 'property', 'article:published_time', $get( 'published' ) );
			$tags[] = array( 'property', 'article:modified_time', $get( 'modified' ) );
		}
		$tags[] = array( 'name', 'twitter:card', '' !== $get( 'image' ) ? 'summary_large_image' : 'summary' );
		$tags[] = array( 'name', 'twitter:title', $get( 'title' ) );
		$tags[] = array( 'name', 'twitter:description', $get( 'description' ) );
		$tags[] = array( 'name', 'twitter:image', $get( 'image' ) );
		$tags[] = array( 'name', 'twitter:site', $get( 'twitter_site' ) );

		$result = array();
		foreach ( $tags as $tag ) {
			if ( '' !== $tag[2] ) {
				$result[] = array(
					$tag[0]   => $tag[1],
					'content' => $tag[2],
				);
			}
		}
		return $result;
	}
}
