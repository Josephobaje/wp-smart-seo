<?php

declare(strict_types=1);

namespace WP_Smart_SEO\Tests\Unit;

use WP_Smart_SEO\Text;

final class TextTest extends TestCase {

	public function test_clean_strips_html_shortcodes_blocks_and_entities(): void {
		$html = "<!-- wp:paragraph --><p>Hello&nbsp;<strong>Lagos</strong> &amp; Abuja</p>\n[gallery ids=\"1,2\"]<!-- /wp:paragraph -->";
		$this->assertSame( 'Hello Lagos & Abuja', Text::clean( $html ) );
	}

	public function test_squish_collapses_all_whitespace(): void {
		$this->assertSame( 'a b c', Text::squish( "  a \n\t b\u{00A0}\u{00A0}c  " ) );
	}

	public function test_truncate_keeps_short_text(): void {
		$this->assertSame( 'Short text', Text::truncate( 'Short text', 160 ) );
	}

	public function test_truncate_cuts_on_word_boundary_with_ellipsis(): void {
		$text   = 'WordPress SEO made simple for small businesses in Nigeria and beyond';
		$result = Text::truncate( $text, 30 );
		$this->assertSame( 'WordPress SEO made simple for…', $result );
		$this->assertLessThanOrEqual( 30, mb_strlen( $result ) );
	}

	public function test_truncate_is_multibyte_safe(): void {
		$result = Text::truncate( str_repeat( 'ñ', 50 ), 10 );
		$this->assertSame( 10, mb_strlen( $result ) );
		$this->assertStringEndsWith( '…', $result );
	}

	public function test_truncate_removes_trailing_punctuation_before_ellipsis(): void {
		$this->assertSame( 'One, two…', Text::truncate( 'One, two, three, four', 11 ) );
	}
}
