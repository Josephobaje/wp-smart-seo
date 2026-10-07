<?php

declare(strict_types=1);

namespace WP_Smart_SEO\Tests\Unit;

use Brain\Monkey\Functions;
use WP_Post;
use WP_Smart_SEO\Head_Output;
use WP_Smart_SEO\Meta_Resolver;
use WP_Smart_SEO\Post_Meta;
use WP_Smart_SEO\Settings;

final class HeadOutputTest extends TestCase {

	private Head_Output $head;

	private WP_Post $post;

	protected function setUp(): void {
		parent::setUp();
		$this->stub_site();
		Functions\when( 'get_option' )->justReturn(
			array(
				'twitter_site'   => '@josephobaje',
				'schema_name'    => 'Joseph Obaje',
				'schema_same_as' => array( 'https://github.com/Josephobaje' ),
			)
		);
		$this->post = new WP_Post(
			array(
				'ID'           => 42,
				'post_title'   => 'Flutter & "Security" tips',
				'post_content' => '<p>Store tokens securely and pin certificates.</p>',
				'post_type'    => 'post',
			)
		);
		Functions\when( 'is_singular' )->justReturn( true );
		Functions\when( 'get_queried_object' )->justReturn( $this->post );
		Functions\when( 'is_search' )->justReturn( false );
		Functions\when( 'is_date' )->justReturn( false );
		Functions\when( 'is_404' )->justReturn( false );
		Functions\when( 'get_query_var' )->justReturn( 0 );
		Functions\when( 'get_the_title' )->justReturn( 'Flutter & "Security" tips' );
		Functions\when( 'get_permalink' )->justReturn( 'https://example.com/flutter-security/' );
		Functions\when( 'get_post_time' )->justReturn( '2026-10-01T09:00:00+00:00' );
		Functions\when( 'get_post_modified_time' )->justReturn( '2026-10-02T09:00:00+00:00' );
		Functions\when( 'get_the_post_thumbnail_url' )->justReturn( 'https://example.com/flutter.jpg' );
		Functions\when( 'get_userdata' )->justReturn( (object) array( 'ID' => 1, 'display_name' => 'Joseph Obaje' ) );
		Functions\when( 'get_author_posts_url' )->justReturn( 'https://example.com/author/joseph/' );
		Functions\when( 'get_the_category' )->justReturn( array( (object) array( 'name' => 'Mobile' ) ) );
		Functions\when( 'get_the_tags' )->justReturn( false );
		$this->stub_meta( array() );

		$settings   = new Settings();
		$this->head = new Head_Output( $settings, new Meta_Resolver( $settings ) );
	}

	public function test_document_title_on_single_post(): void {
		$this->assertSame( 'Flutter & "Security" tips – Obaje Tech', $this->head->document_title( '' ) );
	}

	public function test_document_title_untouched_on_other_views(): void {
		Functions\when( 'is_singular' )->justReturn( false );
		Functions\when( 'is_front_page' )->justReturn( false );
		Functions\when( 'is_home' )->justReturn( false );
		$this->assertSame( 'Archive title', $this->head->document_title( 'Archive title' ) );
	}

	public function test_robots_filter(): void {
		$this->assertSame( array( 'max-image-preview' => 'large' ), $this->head->robots( array() ) );

		$this->stub_meta( array( 42 => array( Post_Meta::NOINDEX => '1' ) ) );
		$this->assertSame( array( 'noindex' => true, 'follow' => true ), $this->head->robots( array( 'max-image-preview' => 'large' ) ) );
	}

	public function test_print_head_outputs_escaped_tags_and_json_ld(): void {
		ob_start();
		$this->head->print_head();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( '<meta name="description" content="Store tokens securely and pin certificates." />', $html );
		$this->assertStringContainsString( '<link rel="canonical" href="https://example.com/flutter-security/" />', $html );
		$this->assertStringContainsString( '<meta property="og:type" content="article" />', $html );
		$this->assertStringContainsString( 'content="Flutter &amp; &quot;Security&quot; tips – Obaje Tech"', $html );
		$this->assertStringContainsString( '<meta name="twitter:card" content="summary_large_image" />', $html );
		$this->assertStringContainsString( '<meta name="twitter:site" content="@josephobaje" />', $html );

		$this->assertSame( 1, preg_match( '#<script type="application/ld\+json">(.+?)</script>#', $html, $m ) );
		$graph = json_decode( $m[1], true );
		$types = array_column( $graph['@graph'], '@type' );
		$this->assertSame( array( 'Organization', 'WebSite', 'BlogPosting' ), $types );
		$this->assertSame( 'Joseph Obaje', $graph['@graph'][0]['name'] );
		$this->assertSame( 'Mobile', $graph['@graph'][2]['articleSection'] );
	}

	public function test_noindexed_post_has_no_canonical(): void {
		$this->stub_meta( array( 42 => array( Post_Meta::NOINDEX => '1' ) ) );
		ob_start();
		$this->head->print_head();
		$html = (string) ob_get_clean();
		$this->assertStringNotContainsString( 'rel="canonical"', $html );
	}

	public function test_register_hooks_replaces_core_canonical(): void {
		Functions\expect( 'remove_action' )->once()->with( 'wp_head', 'rel_canonical' );
		$this->head->register_hooks();
		$this->assertNotFalse( has_action( 'wp_head', array( $this->head, 'print_head' ) ) );
		$this->assertNotFalse( has_filter( 'pre_get_document_title', array( $this->head, 'document_title' ) ) );
	}
}
