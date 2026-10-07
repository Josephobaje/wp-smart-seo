<?php

declare(strict_types=1);

namespace WP_Smart_SEO\Tests\Unit;

use Brain\Monkey\Functions;
use WP_Post;
use WP_Smart_SEO\Meta_Box;
use WP_Smart_SEO\Meta_Resolver;
use WP_Smart_SEO\Post_Meta;
use WP_Smart_SEO\Settings;

final class MetaBoxTest extends TestCase {

	private Meta_Box $box;

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'get_option' )->justReturn( array() );
		Functions\when( 'wp_unslash' )->returnArg();
		Functions\when( 'wp_is_post_revision' )->justReturn( false );
		$this->box = new Meta_Box( new Meta_Resolver( new Settings() ) );
	}

	private function valid_request(): void {
		$_POST = array(
			Meta_Box::NONCE_FIELD => 'good-nonce',
			'wpss_title'          => ' <b>My SEO title</b> ',
			'wpss_description'    => 'A description',
			'wpss_canonical'      => 'javascript:alert(1)',
			'wpss_noindex'        => '1',
		);
		Functions\when( 'wp_verify_nonce' )->alias(
			static function ( $nonce, $action ) {
				return 'good-nonce' === $nonce && Meta_Box::NONCE_ACTION === $action ? 1 : false;
			}
		);
	}

	public function test_save_requires_nonce(): void {
		Functions\expect( 'update_post_meta' )->never();
		$this->assertFalse( $this->box->save( 5, new WP_Post() ) );
	}

	public function test_save_rejects_invalid_nonce(): void {
		$this->valid_request();
		$_POST[ Meta_Box::NONCE_FIELD ] = 'forged';
		Functions\expect( 'update_post_meta' )->never();
		$this->assertFalse( $this->box->save( 5, new WP_Post() ) );
	}

	public function test_save_requires_capability(): void {
		$this->valid_request();
		Functions\expect( 'current_user_can' )->once()->with( 'edit_post', 5 )->andReturn( false );
		Functions\expect( 'update_post_meta' )->never();
		$this->assertFalse( $this->box->save( 5, new WP_Post() ) );
	}

	public function test_save_skips_revisions(): void {
		$this->valid_request();
		Functions\when( 'wp_is_post_revision' )->justReturn( 4 );
		Functions\expect( 'update_post_meta' )->never();
		$this->assertFalse( $this->box->save( 5, new WP_Post() ) );
	}

	public function test_save_sanitizes_and_deletes_empty_values(): void {
		$this->valid_request();
		Functions\when( 'current_user_can' )->justReturn( true );

		$saved   = array();
		$deleted = array();
		Functions\when( 'update_post_meta' )->alias(
			static function ( $id, $key, $value ) use ( &$saved ) {
				$saved[ $key ] = $value;
				return true;
			}
		);
		Functions\when( 'delete_post_meta' )->alias(
			static function ( $id, $key ) use ( &$deleted ) {
				$deleted[] = $key;
				return true;
			}
		);

		$this->assertTrue( $this->box->save( 5, new WP_Post() ) );
		$this->assertSame(
			array(
				Post_Meta::TITLE       => 'My SEO title',
				Post_Meta::DESCRIPTION => 'A description',
				Post_Meta::NOINDEX     => true,
			),
			$saved
		);
		$this->assertSame( array( Post_Meta::CANONICAL ), $deleted, 'Invalid canonical URL is removed' );
	}

	public function test_render_outputs_nonce_and_escaped_values(): void {
		$this->stub_site();
		$this->stub_meta( array( 9 => array( Post_Meta::TITLE => '"><script>alert(1)</script>' ) ) );
		Functions\when( 'get_query_var' )->justReturn( 0 );
		Functions\when( 'get_the_title' )->justReturn( 'Post' );
		Functions\when( 'get_permalink' )->justReturn( 'https://example.com/post/' );
		Functions\expect( 'wp_nonce_field' )->once()->with( Meta_Box::NONCE_ACTION, Meta_Box::NONCE_FIELD );
		Functions\when( 'checked' )->justReturn( '' );

		ob_start();
		$this->box->render( new WP_Post( array( 'ID' => 9, 'post_content' => 'Body text' ) ) );
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'name="wpss_title"', $html );
		$this->assertStringNotContainsString( '<script>alert(1)</script>', $html );
		$this->assertStringContainsString( '&lt;script&gt;', $html );
	}
}
