<?php

declare(strict_types=1);

namespace WP_Smart_SEO\Tests\Unit;

use Brain\Monkey\Functions;
use WP_Smart_SEO\Settings;
use WP_Smart_SEO\Sitemap;

final class SitemapTest extends TestCase {

	public function test_urlset_is_valid_xml_and_escapes_urls(): void {
		$xml = Sitemap::render_urlset(
			array(
				array(
					'loc'     => 'https://example.com/?p=1&lang=en',
					'lastmod' => '2026-10-07 08:00:00',
				),
				array( 'loc' => 'https://example.com/about/' ),
				array( 'loc' => '' ),
				array(
					'loc'     => 'https://example.com/bad-date/',
					'lastmod' => 'not a date',
				),
			)
		);

		$this->assertStringContainsString( '<loc>https://example.com/?p=1&amp;lang=en</loc>', $xml );
		$doc = simplexml_load_string( $xml );
		$this->assertNotFalse( $doc );
		$this->assertSame( 'urlset', $doc->getName() );
		$this->assertCount( 3, $doc->url, 'Entries without a URL are skipped' );
		$this->assertSame( '2026-10-07T08:00:00+00:00', (string) $doc->url[0]->lastmod );
		$this->assertStringNotContainsString( '<lastmod></lastmod>', $xml );
	}

	public function test_index_document(): void {
		$xml = Sitemap::render_index( array( array( 'loc' => 'https://example.com/smart-sitemap-post-1.xml' ) ) );
		$doc = simplexml_load_string( $xml );
		$this->assertNotFalse( $doc );
		$this->assertSame( 'sitemapindex', $doc->getName() );
		$this->assertSame( Sitemap::XMLNS, $doc->getNamespaces()[''] );
		$this->assertSame( 'https://example.com/smart-sitemap-post-1.xml', (string) $doc->sitemap[0]->loc );
	}

	public function test_page_count(): void {
		$this->assertSame( 1, Sitemap::page_count( 0 ) );
		$this->assertSame( 1, Sitemap::page_count( 1000 ) );
		$this->assertSame( 2, Sitemap::page_count( 1001 ) );
		$this->assertSame( 1, Sitemap::page_count( -5 ) );
	}

	public function test_w3c_date_and_xml_escape(): void {
		$this->assertSame( '2026-01-02T03:04:05+00:00', Sitemap::w3c_date( '2026-01-02 03:04:05 UTC' ) );
		$this->assertSame( '', Sitemap::w3c_date( 'garbage' ) );
		$this->assertSame( '&lt;a&gt; &amp; &quot;b&quot; &apos;c&apos;', Sitemap::xml( '<a> & "b" \'c\'' ) );
	}

	public function test_query_vars_and_rewrite_rules(): void {
		$this->assertSame( array( 'p', Sitemap::QUERY_VAR, Sitemap::PAGE_QUERY_VAR ), Sitemap::query_vars( array( 'p' ) ) );

		Functions\expect( 'add_rewrite_rule' )->twice();
		Sitemap::add_rewrite_rules();
	}

	public function test_robots_txt_lists_sitemap_only_when_public_and_enabled(): void {
		Functions\when( 'get_option' )->justReturn( array() );
		$sitemap = new Sitemap( new Settings() );

		$this->assertStringContainsString(
			'Sitemap: https://example.com/smart-sitemap.xml',
			$sitemap->robots_txt( "User-agent: *\nDisallow: /wp-admin/\n", true )
		);
		$this->assertSame( 'User-agent: *', $sitemap->robots_txt( 'User-agent: *', false ) );

		Functions\when( 'get_option' )->justReturn( array( 'sitemap_enabled' => false ) );
		$this->assertSame( 'x', ( new Sitemap( new Settings() ) )->robots_txt( 'x', true ) );
	}

	public function test_register_hooks_disables_core_sitemap_by_default(): void {
		Functions\when( 'get_option' )->justReturn( array() );
		( new Sitemap( new Settings() ) )->register_hooks();
		$this->assertNotFalse( has_filter( 'wp_sitemaps_enabled', '__return_false' ) );
		$this->assertNotFalse( has_action( 'template_redirect' ) );
	}

	public function test_non_sitemap_requests_are_ignored(): void {
		Functions\when( 'get_option' )->justReturn( array() );
		Functions\when( 'get_query_var' )->justReturn( '' );
		Functions\expect( 'status_header' )->never();
		( new Sitemap( new Settings() ) )->maybe_render();
		$this->addToAssertionCount( 1 );
	}
}
