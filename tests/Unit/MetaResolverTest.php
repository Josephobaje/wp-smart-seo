<?php

declare(strict_types=1);

namespace WP_Smart_SEO\Tests\Unit;

use Brain\Monkey\Functions;
use PHPUnit\Framework\Attributes\DataProvider;
use WP_Post;
use WP_Smart_SEO\Meta_Resolver;
use WP_Smart_SEO\Post_Meta;
use WP_Smart_SEO\Settings;

final class MetaResolverTest extends TestCase {

	private Meta_Resolver $resolver;

	protected function setUp(): void {
		parent::setUp();
		$this->stub_site();
		Functions\when( 'get_option' )->justReturn( array() );
		Functions\when( 'get_query_var' )->justReturn( 0 );
		Functions\when( 'get_the_title' )->alias(
			static function ( $post ) {
				return $post->post_title;
			}
		);
		$this->resolver = new Meta_Resolver( new Settings() );
	}

	private function post( array $data = array() ): WP_Post {
		return new WP_Post(
			array_merge(
				array(
					'ID'           => 7,
					'post_title'   => 'Securing WordPress in 2026',
					'post_content' => '<p>Keep plugins updated, use strong passwords and enable two-factor authentication.</p>',
				),
				$data
			)
		);
	}

	/**
	 * @param array<string,string> $vars
	 */
	#[DataProvider( 'templates' )]
	public function test_render_template( string $template, array $vars, string $expected ): void {
		$this->assertSame( $expected, Meta_Resolver::render_template( $template, $vars ) );
	}

	/**
	 * @return array<string, array{string, array<string,string>, string}>
	 */
	public static function templates(): array {
		$vars = array(
			'title'    => 'Hello',
			'sitename' => 'Obaje Tech',
			'tagline'  => 'Secure apps',
			'sep'      => '–',
			'page'     => '',
		);
		return array(
			'post'             => array( '%title% %sep% %sitename%', $vars, 'Hello – Obaje Tech' ),
			'home'             => array( '%sitename% %sep% %tagline%', $vars, 'Obaje Tech – Secure apps' ),
			'empty tagline'    => array( '%sitename% %sep% %tagline%', array_merge( $vars, array( 'tagline' => '' ) ), 'Obaje Tech' ),
			'empty page'       => array( '%title% %sep% %page% %sep% %sitename%', $vars, 'Hello – Obaje Tech' ),
			'with page'        => array( '%title% %sep% %page% %sep% %sitename%', array_merge( $vars, array( 'page' => 'Page 2' ) ), 'Hello – Page 2 – Obaje Tech' ),
			'leading sep'      => array( '%sep% %title%', $vars, 'Hello' ),
			'unknown variable' => array( '%title% %foo%', $vars, 'Hello' ),
			'custom separator' => array( '%title% %sep% %sitename%', array_merge( $vars, array( 'sep' => '|' ) ), 'Hello | Obaje Tech' ),
			'literal text'     => array( 'Read: %title%', $vars, 'Read: Hello' ),
		);
	}

	public function test_post_title_prefers_custom_title(): void {
		$this->stub_meta( array( 7 => array( Post_Meta::TITLE => 'Custom SEO Title' ) ) );
		$this->assertSame( 'Custom SEO Title', $this->resolver->post_title( $this->post() ) );
	}

	public function test_post_title_uses_template(): void {
		$this->stub_meta( array() );
		$this->assertSame( 'Securing WordPress in 2026 – Obaje Tech', $this->resolver->post_title( $this->post() ) );
	}

	public function test_home_title_and_description_fallback_to_tagline(): void {
		$this->assertSame( 'Obaje Tech – Secure web and mobile apps', $this->resolver->home_title() );
		$this->assertSame( 'Secure web and mobile apps', $this->resolver->home_description() );
	}

	public function test_description_priority_custom_then_excerpt_then_content(): void {
		$this->stub_meta( array( 7 => array( Post_Meta::DESCRIPTION => 'Custom description' ) ) );
		$this->assertSame( 'Custom description', $this->resolver->post_description( $this->post() ) );

		$this->stub_meta( array() );
		$this->assertSame( 'A short excerpt.', $this->resolver->post_description( $this->post( array( 'post_excerpt' => ' A short <b>excerpt</b>. ' ) ) ) );
		$this->assertSame(
			'Keep plugins updated, use strong passwords and enable two-factor authentication.',
			$this->resolver->post_description( $this->post() )
		);
	}

	public function test_long_content_is_truncated_to_160_characters(): void {
		$this->stub_meta( array() );
		$post        = $this->post( array( 'post_content' => str_repeat( 'Lagos tech scene is growing fast. ', 20 ) ) );
		$description = $this->resolver->post_description( $post );
		$this->assertLessThanOrEqual( 160, mb_strlen( $description ) );
		$this->assertStringEndsWith( '…', $description );
	}

	public function test_canonical_override_and_pagination(): void {
		Functions\when( 'get_permalink' )->justReturn( 'https://example.com/securing-wordpress/' );

		$this->stub_meta( array( 7 => array( Post_Meta::CANONICAL => 'https://medium.com/@me/original' ) ) );
		$this->assertSame( 'https://medium.com/@me/original', $this->resolver->post_canonical( $this->post() ) );

		$this->stub_meta( array() );
		$this->assertSame( 'https://example.com/securing-wordpress/', $this->resolver->post_canonical( $this->post() ) );

		Functions\when( 'get_query_var' )->alias(
			static function ( $name ) {
				return 'page' === $name ? 3 : 0;
			}
		);
		$this->assertSame( 'https://example.com/securing-wordpress/3/', $this->resolver->post_canonical( $this->post() ) );
	}

	public function test_should_noindex(): void {
		Functions\when( 'is_search' )->justReturn( false );
		Functions\when( 'is_date' )->justReturn( false );
		Functions\when( 'is_404' )->justReturn( false );

		$this->stub_meta( array( 7 => array( Post_Meta::NOINDEX => '1' ) ) );
		$this->assertTrue( $this->resolver->should_noindex( $this->post() ) );

		$this->stub_meta( array() );
		$this->assertFalse( $this->resolver->should_noindex( $this->post() ) );
		$this->assertFalse( $this->resolver->should_noindex() );

		Functions\when( 'is_search' )->justReturn( true );
		$this->assertTrue( $this->resolver->should_noindex() );
	}

	public function test_search_pages_can_be_indexed_when_disabled(): void {
		Functions\when( 'get_option' )->justReturn( array( 'noindex_search' => false ) );
		Functions\when( 'is_search' )->justReturn( true );
		Functions\when( 'is_date' )->justReturn( false );
		Functions\when( 'is_404' )->justReturn( false );
		$this->assertFalse( ( new Meta_Resolver( new Settings() ) )->should_noindex() );
	}

	public function test_social_tags_for_article_with_image(): void {
		$tags = Meta_Resolver::social_tags(
			array(
				'type'         => 'article',
				'title'        => 'Hello',
				'description'  => 'World',
				'url'          => 'https://example.com/hello/',
				'site_name'    => 'Obaje Tech',
				'image'        => 'https://example.com/hero.jpg',
				'locale'       => 'en_NG',
				'published'    => '2026-10-01T10:00:00+00:00',
				'modified'     => '',
				'twitter_site' => '@josephobaje',
			)
		);
		$flat = array();
		foreach ( $tags as $tag ) {
			$flat[ $tag['property'] ?? $tag['name'] ] = $tag['content'];
		}
		$this->assertSame( 'article', $flat['og:type'] );
		$this->assertSame( 'en_NG', $flat['og:locale'] );
		$this->assertSame( 'summary_large_image', $flat['twitter:card'] );
		$this->assertSame( '@josephobaje', $flat['twitter:site'] );
		$this->assertSame( '2026-10-01T10:00:00+00:00', $flat['article:published_time'] );
		$this->assertArrayNotHasKey( 'article:modified_time', $flat, 'Empty values are skipped' );
	}

	public function test_social_tags_without_image_use_summary_card(): void {
		$tags = Meta_Resolver::social_tags( array( 'title' => 'Hi' ) );
		$this->assertContains( array( 'name' => 'twitter:card', 'content' => 'summary' ), $tags );
		$this->assertContains( array( 'property' => 'og:type', 'content' => 'website' ), $tags );
	}
}
