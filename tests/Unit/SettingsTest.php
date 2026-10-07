<?php

declare(strict_types=1);

namespace WP_Smart_SEO\Tests\Unit;

use Brain\Monkey\Functions;
use WP_Smart_SEO\Settings;

final class SettingsTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'get_post_types' )->justReturn(
			array(
				'post'       => 'post',
				'page'       => 'page',
				'attachment' => 'attachment',
				'product'    => 'product',
			)
		);
	}

	public function test_all_merges_stored_values_with_defaults(): void {
		Functions\expect( 'get_option' )->once()->with( Settings::OPTION, array() )->andReturn( array( 'separator' => '|' ) );
		$settings = new Settings();
		$this->assertSame( '|', $settings->get( 'separator' ) );
		$this->assertTrue( $settings->get( 'sitemap_enabled' ) );
		$this->assertNull( $settings->get( 'unknown' ) );
		// Cached: get_option is only called once.
		$this->assertSame( '%title% %sep% %sitename%', $settings->get( 'title_template' ) );
	}

	public function test_corrupt_option_falls_back_to_defaults(): void {
		Functions\when( 'get_option' )->justReturn( 'not-an-array' );
		$this->assertSame( Settings::defaults(), ( new Settings() )->all() );
	}

	public function test_sanitize_validates_every_field(): void {
		$clean = Settings::sanitize(
			array(
				'separator'           => '<b>',
				'title_template'      => '  <em>%title%</em>   | %sitename% ',
				'home_title_template' => '',
				'home_description'    => "Secure apps\n\n<script>x</script>for Nigeria",
				'default_og_image'    => 'javascript:alert(1)',
				'schema_logo'         => 'https://example.com/logo.png',
				'twitter_site'        => 'https://x.com/josephobaje?ref=1',
				'schema_type'         => 'Robot',
				'schema_name'         => ' Joseph   Obaje ',
				'schema_same_as'      => "https://www.linkedin.com/in/joseph-obaje\nftp://bad.example\n\nhttps://github.com/Josephobaje\nhttps://github.com/Josephobaje",
				'sitemap_enabled'     => '1',
				'sitemap_post_types'  => array( 'post', 'attachment', 'secret', 'product' ),
				'evil_key'            => 'dropped',
			)
		);

		$this->assertSame( '–', $clean['separator'] );
		$this->assertSame( '%title% | %sitename%', $clean['title_template'] );
		$this->assertSame( '%sitename% %sep% %tagline%', $clean['home_title_template'] );
		$this->assertSame( 'Secure apps xfor Nigeria', $clean['home_description'] );
		$this->assertSame( '', $clean['default_og_image'] );
		$this->assertSame( 'https://example.com/logo.png', $clean['schema_logo'] );
		$this->assertSame( '@josephobaje', $clean['twitter_site'] );
		$this->assertSame( 'Organization', $clean['schema_type'] );
		$this->assertSame( 'Joseph Obaje', $clean['schema_name'] );
		$this->assertSame(
			array( 'https://www.linkedin.com/in/joseph-obaje', 'https://github.com/Josephobaje' ),
			$clean['schema_same_as']
		);
		$this->assertTrue( $clean['sitemap_enabled'] );
		$this->assertFalse( $clean['disable_core_sitemap'] );
		$this->assertFalse( $clean['noindex_search'] );
		$this->assertSame( array( 'post', 'product' ), $clean['sitemap_post_types'] );
		$this->assertArrayNotHasKey( 'evil_key', $clean );
	}

	public function test_sanitize_handles_non_array_input(): void {
		$clean = Settings::sanitize( 'nope' );
		$this->assertSame( '–', $clean['separator'] );
		$this->assertSame( array(), $clean['sitemap_post_types'] );
	}

	#[\PHPUnit\Framework\Attributes\DataProvider( 'twitter_handles' )]
	public function test_twitter_handle_normalisation( string $input, string $expected ): void {
		$this->assertSame( $expected, Settings::sanitize_twitter_handle( $input ) );
	}

	/**
	 * @return array<string, array{string, string}>
	 */
	public static function twitter_handles(): array {
		return array(
			'plain'          => array( 'josephobaje', '@josephobaje' ),
			'with at'        => array( '@JosephObaje', '@JosephObaje' ),
			'twitter url'    => array( 'https://twitter.com/joseph_dev/', '@joseph_dev' ),
			'x url'          => array( 'https://www.x.com/joseph_dev?lang=en', '@joseph_dev' ),
			'too long'       => array( 'this_handle_is_way_too_long', '' ),
			'invalid chars'  => array( 'bad-handle!', '' ),
			'empty'          => array( '', '' ),
		);
	}
}
