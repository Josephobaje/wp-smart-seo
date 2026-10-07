<?php

declare(strict_types=1);

namespace WP_Smart_SEO\Tests\Unit;

use WP_Smart_SEO\Schema;

final class SchemaTest extends TestCase {

	public function test_organization_publisher(): void {
		$node = Schema::publisher(
			array(
				'type'    => 'Organization',
				'name'    => 'Obaje Tech',
				'url'     => 'https://example.com/',
				'logo'    => 'https://example.com/logo.png',
				'same_as' => array( 'https://github.com/Josephobaje' ),
			)
		);
		$this->assertSame( 'Organization', $node['@type'] );
		$this->assertSame( 'https://example.com/#organization', $node['@id'] );
		$this->assertSame( 'https://example.com/logo.png', $node['logo']['url'] );
		$this->assertSame( array( 'https://github.com/Josephobaje' ), $node['sameAs'] );
	}

	public function test_person_publisher_uses_image_and_rejects_unknown_types(): void {
		$person = Schema::publisher( array( 'type' => 'Person', 'name' => 'Joseph Obaje', 'url' => 'https://example.com/', 'logo' => 'https://example.com/me.jpg' ) );
		$this->assertSame( 'https://example.com/#person', $person['@id'] );
		$this->assertArrayHasKey( 'image', $person );
		$this->assertArrayNotHasKey( 'logo', $person );

		$fallback = Schema::publisher( array( 'type' => 'Alien', 'url' => 'https://example.com/' ) );
		$this->assertSame( 'Organization', $fallback['@type'] );
		$this->assertArrayNotHasKey( 'sameAs', $fallback );
	}

	public function test_website_has_search_action(): void {
		$node = Schema::website( 'Obaje Tech', 'https://example.com/', 'Secure apps', 'https://example.com/#organization' );
		$this->assertSame( 'https://example.com/?s={search_term_string}', $node['potentialAction']['target']['urlTemplate'] );
		$this->assertSame( array( '@id' => 'https://example.com/#organization' ), $node['publisher'] );
		$this->assertSame( 'Secure apps', $node['description'] );
		$this->assertArrayNotHasKey( 'description', Schema::website( 'X', 'https://x.test', '', '#o' ) );
	}

	public function test_article_node(): void {
		$node = Schema::article(
			array(
				'type'         => 'BlogPosting',
				'url'          => 'https://example.com/post/',
				'headline'     => str_repeat( 'Very long headline ', 10 ),
				'description'  => 'Summary',
				'published'    => '2026-10-01T10:00:00+00:00',
				'modified'     => '2026-10-02T10:00:00+00:00',
				'author_name'  => 'Joseph Obaje',
				'author_url'   => '',
				'image'        => 'https://example.com/a.jpg',
				'section'      => 'Security',
				'keywords'     => array( 'wordpress', 'seo' ),
				'website_id'   => 'https://example.com/#website',
				'publisher_id' => 'https://example.com/#organization',
			)
		);
		$this->assertSame( 'BlogPosting', $node['@type'] );
		$this->assertLessThanOrEqual( Schema::HEADLINE_LENGTH, mb_strlen( $node['headline'] ) );
		$this->assertSame( array( '@type' => 'Person', 'name' => 'Joseph Obaje' ), $node['author'] );
		$this->assertSame( 'wordpress, seo', $node['keywords'] );
		$this->assertSame( array( '@id' => 'https://example.com/post/' ), $node['mainEntityOfPage'] );
		$this->assertSame( 'Security', $node['articleSection'] );
	}

	public function test_article_defaults_and_drops_empty_values(): void {
		$node = Schema::article( array( 'type' => 'Recipe', 'url' => 'https://example.com/p/', 'headline' => 'Hi' ) );
		$this->assertSame( 'Article', $node['@type'] );
		$this->assertArrayNotHasKey( 'datePublished', $node );
		$this->assertArrayNotHasKey( 'author', $node );
	}

	public function test_graph_and_safe_encoding(): void {
		$graph = Schema::graph( array( array( '@type' => 'Thing', 'name' => '</script><script>alert("x")</script> & co' ), array() ) );
		$this->assertSame( 'https://schema.org', $graph['@context'] );
		$this->assertCount( 1, $graph['@graph'] );

		$json = Schema::encode( $graph );
		$this->assertStringNotContainsString( '</script>', $json );
		$this->assertStringContainsString( '\u003C/script\u003E', $json );
		$this->assertStringContainsString( 'https://schema.org', $json, 'Slashes stay readable' );
		$this->assertSame( $graph, json_decode( $json, true ) );
	}

	public function test_id_strips_existing_fragment(): void {
		$this->assertSame( 'https://example.com/#website', Schema::id( 'https://example.com/#old', 'website' ) );
	}
}
