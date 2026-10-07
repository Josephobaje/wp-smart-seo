<?php
/**
 * Text helpers used to build titles and descriptions.
 *
 * @package WP_Smart_SEO
 */

namespace WP_Smart_SEO;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Small, side-effect-free string utilities.
 */
final class Text {

	/**
	 * Turns post content (HTML, shortcodes, blocks) into a single line of plain text.
	 *
	 * @param string $content Raw content.
	 * @return string
	 */
	public static function clean( $content ) {
		$text = strip_shortcodes( (string) $content );
		// Remove block comments and HTML, then decode entities such as &amp; and &#8217;.
		$text = preg_replace( '/<!--.*?-->/s', ' ', $text );
		$text = wp_strip_all_tags( (string) $text, true );
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		return self::squish( $text );
	}

	/**
	 * Collapses runs of whitespace (including non-breaking spaces) into single spaces.
	 *
	 * @param string $text Input text.
	 * @return string
	 */
	public static function squish( $text ) {
		$text = preg_replace( '/[\s\x{00A0}]+/u', ' ', (string) $text );
		return trim( (string) $text );
	}

	/**
	 * Shortens text to at most $max characters, cutting on a word boundary and
	 * appending an ellipsis when something was removed.
	 *
	 * @param string $text Plain text.
	 * @param int    $max  Maximum length in characters (multibyte safe).
	 * @return string
	 */
	public static function truncate( $text, $max = 160 ) {
		$text = self::squish( $text );
		$max  = max( 1, (int) $max );
		if ( mb_strlen( $text, 'UTF-8' ) <= $max ) {
			return $text;
		}
		$cut   = mb_substr( $text, 0, $max - 1, 'UTF-8' );
		$next  = mb_substr( $text, $max - 1, 1, 'UTF-8' );
		$space = mb_strrpos( $cut, ' ', 0, 'UTF-8' );
		if ( ' ' !== $next && false !== $space && $space > (int) ( $max * 0.6 ) ) {
			$cut = mb_substr( $cut, 0, $space, 'UTF-8' );
		}
		return rtrim( $cut, " \t\n\r\0\x0B,.;:-–—" ) . '…';
	}
}
