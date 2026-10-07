<?php

declare(strict_types=1);

namespace WP_Smart_SEO\Tests\Unit;

use Brain\Monkey\Functions;
use WP_Smart_SEO\Post_Meta;

final class PostMetaTest extends TestCase {

	public function test_title_is_cleaned_and_capped(): void {
		$this->assertSame( 'Hello World', Post_Meta::sanitize_title( "  <b>Hello</b>\n World " ) );
		$this->assertSame( Post_Meta::MAX_TITLE, mb_strlen( Post_Meta::sanitize_title( str_repeat( 'é', 300 ) ) ) );
	}

	public function test_description_is_cleaned_and_capped(): void {
		$this->assertSame( 'Line one xline two', Post_Meta::sanitize_description( "Line one\n<script>x</script>line two" ) );
		$this->assertSame( Post_Meta::MAX_DESCRIPTION, mb_strlen( Post_Meta::sanitize_description( str_repeat( 'a ', 400 ) ) ) );
	}

	public function test_canonical_only_accepts_http_urls(): void {
		$this->assertSame( 'https://example.com/a', Post_Meta::sanitize_canonical( ' https://example.com/a ' ) );
		$this->assertSame( '', Post_Meta::sanitize_canonical( 'javascript:alert(1)' ) );
		$this->assertSame( '', Post_Meta::sanitize_canonical( 'ftp://example.com/file' ) );
	}

	public function test_flags(): void {
		foreach ( array( true, 1, '1', 'on', 'yes', 'true' ) as $truthy ) {
			$this->assertTrue( Post_Meta::sanitize_flag( $truthy ) );
		}
		foreach ( array( false, 0, '0', '', 'no', null, array() ) as $falsy ) {
			$this->assertFalse( Post_Meta::sanitize_flag( $falsy ) );
		}
	}

	public function test_get_returns_strings_only(): void {
		$this->stub_meta( array( 3 => array( Post_Meta::TITLE => array( 'not', 'scalar' ), Post_Meta::DESCRIPTION => 'ok' ) ) );
		$this->assertSame( '', Post_Meta::get( 3, Post_Meta::TITLE ) );
		$this->assertSame( 'ok', Post_Meta::get( 3, Post_Meta::DESCRIPTION ) );
	}

	public function test_register_registers_each_key_for_public_post_types(): void {
		Functions\when( 'get_post_types' )->justReturn( array( 'post' => 'post', 'page' => 'page' ) );
		Functions\expect( 'register_post_meta' )->times( 8 )->andReturn( true );
		Post_Meta::register();
	}

	public function test_auth_callback_checks_edit_capability(): void {
		Functions\expect( 'current_user_can' )->once()->with( 'edit_post', 12 )->andReturn( false );
		$this->assertFalse( Post_Meta::can_edit( true, Post_Meta::TITLE, 12 ) );
	}
}
