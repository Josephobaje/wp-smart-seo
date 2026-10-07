<?php

declare(strict_types=1);

namespace WP_Smart_SEO\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

/**
 * Base test case: sets up Brain Monkey and common WordPress function stubs.
 */
abstract class TestCase extends PHPUnitTestCase {
	use MockeryPHPUnitIntegration;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\stubTranslationFunctions();
		Functions\stubEscapeFunctions();
		Functions\stubs(
			array(
				'sanitize_text_field'     => static function ( $value ) {
					return trim( (string) preg_replace( '/\s+/', ' ', strip_tags( (string) $value ) ) );
				},
				'sanitize_textarea_field' => static function ( $value ) {
					return trim( strip_tags( (string) $value ) );
				},
				'sanitize_key'            => static function ( $value ) {
					return (string) preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) );
				},
				'esc_url_raw'             => static function ( $url, $protocols = null ) {
					$url    = trim( (string) $url );
					$scheme = strtolower( (string) parse_url( $url, PHP_URL_SCHEME ) );
					$allow  = $protocols ?? array( 'http', 'https' );
					return ( '' !== $url && in_array( $scheme, $allow, true ) ) ? $url : '';
				},
				'wp_strip_all_tags'       => static function ( $text ) {
					return trim( strip_tags( (string) $text ) );
				},
				'strip_shortcodes'        => static function ( $text ) {
					return (string) preg_replace( '/\[\/?[a-z_]+[^\]]*\]/', '', (string) $text );
				},
				'wp_json_encode'          => static function ( $data, $flags = 0 ) {
					return json_encode( $data, $flags );
				},
				'absint'                  => static function ( $value ) {
					return abs( (int) $value );
				},
				'trailingslashit'         => static function ( $value ) {
					return rtrim( (string) $value, '/\\' ) . '/';
				},
				'untrailingslashit'       => static function ( $value ) {
					return rtrim( (string) $value, '/\\' );
				},
				'user_trailingslashit'    => static function ( $value ) {
					return rtrim( (string) $value, '/' ) . '/';
				},
				'home_url'                => static function ( $path = '' ) {
					return 'https://example.com' . ( '' === $path ? '/' : '/' . ltrim( (string) $path, '/' ) );
				},
			)
		);
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		$_POST = array();
		parent::tearDown();
	}

	/**
	 * Stubs get_bloginfo() with a site name and tagline.
	 */
	protected function stub_site( string $name = 'Obaje Tech', string $tagline = 'Secure web and mobile apps' ): void {
		Functions\when( 'get_bloginfo' )->alias(
			static function ( $show = '' ) use ( $name, $tagline ) {
				switch ( $show ) {
					case 'name':
						return $name;
					case 'description':
						return $tagline;
					case 'language':
						return 'en-NG';
					default:
						return '';
				}
			}
		);
	}

	/**
	 * Stubs get_post_meta() from an array of post ID => [ key => value ].
	 *
	 * @param array<int, array<string, mixed>> $meta Meta values.
	 */
	protected function stub_meta( array $meta ): void {
		Functions\when( 'get_post_meta' )->alias(
			static function ( $post_id, $key = '', $single = false ) use ( $meta ) {
				return $meta[ $post_id ][ $key ] ?? '';
			}
		);
	}
}
