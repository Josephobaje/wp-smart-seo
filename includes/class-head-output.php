<?php
/**
 * Prints SEO tags in the document <head> and filters the document title.
 *
 * @package WP_Smart_SEO
 */

namespace WP_Smart_SEO;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Front-end output: title, meta description, canonical, robots, social tags, JSON-LD.
 */
class Head_Output {

	/**
	 * Settings instance.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Resolver instance.
	 *
	 * @var Meta_Resolver
	 */
	private $resolver;

	/**
	 * Constructor.
	 *
	 * @param Settings      $settings Plugin settings.
	 * @param Meta_Resolver $resolver Meta resolver.
	 */
	public function __construct( Settings $settings, Meta_Resolver $resolver ) {
		$this->settings = $settings;
		$this->resolver = $resolver;
	}

	/**
	 * Hooks into WordPress.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_filter( 'pre_get_document_title', array( $this, 'document_title' ), 15 );
		add_filter( 'wp_robots', array( $this, 'robots' ) );
		// We print a canonical for every indexable view, so replace core's singular-only tag.
		remove_action( 'wp_head', 'rel_canonical' );
		add_action( 'wp_head', array( $this, 'print_head' ), 1 );
	}

	/**
	 * The queried post on singular views.
	 *
	 * @return \WP_Post|null
	 */
	private function current_post() {
		if ( ! is_singular() ) {
			return null;
		}
		$post = get_queried_object();
		return $post instanceof \WP_Post ? $post : null;
	}

	/**
	 * Filters the <title> text.
	 *
	 * @param string $title Title computed by other plugins/themes (empty by default).
	 * @return string
	 */
	public function document_title( $title ) {
		$post = $this->current_post();
		if ( $post ) {
			return $this->resolver->post_title( $post );
		}
		if ( is_front_page() || is_home() ) {
			return $this->resolver->home_title();
		}
		return $title;
	}

	/**
	 * Adds noindex to the core robots meta tag when required (WordPress 5.7+).
	 *
	 * @param array<string,bool|string> $robots Robots directives.
	 * @return array<string,bool|string>
	 */
	public function robots( $robots ) {
		if ( $this->resolver->should_noindex( $this->current_post() ) ) {
			$robots['noindex'] = true;
			$robots['follow']  = true;
			unset( $robots['max-image-preview'] );
		} else {
			$robots['max-image-preview'] = 'large';
		}
		return $robots;
	}

	/**
	 * Collects every value needed for the head tags on the current view.
	 *
	 * @return array<string,string>
	 */
	public function context() {
		$post = $this->current_post();
		$data = array(
			'locale'       => str_replace( '-', '_', (string) get_bloginfo( 'language' ) ),
			'site_name'    => (string) get_bloginfo( 'name' ),
			'twitter_site' => (string) $this->settings->get( 'twitter_site' ),
			'image'        => (string) $this->settings->get( 'default_og_image' ),
			'type'         => 'website',
			'title'        => '',
			'description'  => '',
			'url'          => '',
		);

		if ( $post ) {
			$data['type']        = 'post' === $post->post_type ? 'article' : 'website';
			$data['title']       = $this->resolver->post_title( $post );
			$data['description'] = $this->resolver->post_description( $post );
			$data['url']         = $this->resolver->post_canonical( $post );
			$data['published']   = (string) get_post_time( 'c', true, $post );
			$data['modified']    = (string) get_post_modified_time( 'c', true, $post );
			$thumbnail           = get_the_post_thumbnail_url( $post, 'full' );
			if ( $thumbnail ) {
				$data['image'] = (string) $thumbnail;
			}
		} elseif ( is_front_page() || is_home() ) {
			$data['title']       = $this->resolver->home_title();
			$data['description'] = $this->resolver->home_description();
			$data['url']         = home_url( '/' );
		} elseif ( is_category() || is_tag() || is_tax() ) {
			$term = get_queried_object();
			if ( $term instanceof \WP_Term ) {
				$data['title']       = wp_get_document_title();
				$data['description'] = Text::truncate( Text::clean( (string) term_description( $term ) ), Meta_Resolver::DESCRIPTION_LENGTH );
				$link                = get_term_link( $term );
				$data['url']         = is_wp_error( $link ) ? '' : (string) $link;
			}
		}
		return $data;
	}

	/**
	 * Prints description, canonical, Open Graph, Twitter and JSON-LD tags.
	 *
	 * @return void
	 */
	public function print_head() {
		$data    = $this->context();
		$noindex = $this->resolver->should_noindex( $this->current_post() );

		echo "\n<!-- WP Smart SEO -->\n";
		if ( '' !== $data['description'] ) {
			printf( '<meta name="description" content="%s" />' . "\n", esc_attr( $data['description'] ) );
		}
		if ( '' !== $data['url'] && ! $noindex ) {
			printf( '<link rel="canonical" href="%s" />' . "\n", esc_url( $data['url'] ) );
		}
		if ( '' !== $data['title'] ) {
			foreach ( Meta_Resolver::social_tags( $data ) as $tag ) {
				$attribute = isset( $tag['property'] ) ? 'property' : 'name';
				$value     = 'og:url' === $tag[ $attribute ] || 'og:image' === $tag[ $attribute ] || 'twitter:image' === $tag[ $attribute ]
					? esc_url( $tag['content'] )
					: esc_attr( $tag['content'] );
				printf(
					'<meta %1$s="%2$s" content="%3$s" />' . "\n",
					esc_attr( $attribute ),
					esc_attr( $tag[ $attribute ] ),
					$value // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above with esc_url() or esc_attr().
				);
			}
		}
		$graph = $this->schema_graph( $data );
		if ( ! empty( $graph['@graph'] ) ) {
			echo '<script type="application/ld+json">' . Schema::encode( $graph ) . "</script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON is encoded with JSON_HEX_TAG so it cannot close the script element.
		}
		echo "<!-- / WP Smart SEO -->\n\n";
	}

	/**
	 * Builds the JSON-LD graph for the current view.
	 *
	 * @param array<string,string> $data Values from context().
	 * @return array<string,mixed>
	 */
	public function schema_graph( array $data ) {
		$home      = home_url( '/' );
		$name      = (string) $this->settings->get( 'schema_name' );
		$publisher = Schema::publisher(
			array(
				'type'    => $this->settings->get( 'schema_type' ),
				'name'    => '' !== $name ? $name : $data['site_name'],
				'url'     => $home,
				'logo'    => $this->settings->get( 'schema_logo' ),
				'same_as' => $this->settings->get( 'schema_same_as' ),
			)
		);
		$website   = Schema::website( $data['site_name'], $home, (string) get_bloginfo( 'description' ), $publisher['@id'] );
		$nodes     = array( $publisher, $website );

		$post = $this->current_post();
		if ( $post && 'article' === $data['type'] ) {
			$author     = get_userdata( (int) $post->post_author );
			$categories = get_the_category( $post->ID );
			$tags       = get_the_tags( $post->ID );
			$nodes[]    = Schema::article(
				array(
					'type'         => 'BlogPosting',
					'url'          => $data['url'],
					'headline'     => get_the_title( $post ),
					'description'  => $data['description'],
					'published'    => isset( $data['published'] ) ? $data['published'] : '',
					'modified'     => isset( $data['modified'] ) ? $data['modified'] : '',
					'author_name'  => $author ? $author->display_name : '',
					'author_url'   => $author ? get_author_posts_url( $author->ID ) : '',
					'image'        => $data['image'],
					'section'      => ! empty( $categories ) ? $categories[0]->name : '',
					'keywords'     => is_array( $tags ) ? wp_list_pluck( $tags, 'name' ) : array(),
					'website_id'   => $website['@id'],
					'publisher_id' => $publisher['@id'],
				)
			);
		}
		return Schema::graph( $nodes );
	}
}
