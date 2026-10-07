<?php
/**
 * Registration and access of per-post SEO fields.
 *
 * @package WP_Smart_SEO
 */

namespace WP_Smart_SEO;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Per-post SEO meta keys, sanitizers and registration.
 */
final class Post_Meta {

	const TITLE       = '_wpss_title';
	const DESCRIPTION = '_wpss_description';
	const CANONICAL   = '_wpss_canonical';
	const NOINDEX     = '_wpss_noindex';

	/**
	 * Maximum stored lengths. Longer input is trimmed when saving.
	 */
	const MAX_TITLE       = 120;
	const MAX_DESCRIPTION = 320;

	/**
	 * Registers the meta keys for every public post type so they are typed,
	 * sanitized and available in the REST API for users who can edit the post.
	 *
	 * @return void
	 */
	public static function register() {
		$fields = array(
			self::TITLE       => array( 'string', array( __CLASS__, 'sanitize_title' ) ),
			self::DESCRIPTION => array( 'string', array( __CLASS__, 'sanitize_description' ) ),
			self::CANONICAL   => array( 'string', array( __CLASS__, 'sanitize_canonical' ) ),
			self::NOINDEX     => array( 'boolean', array( __CLASS__, 'sanitize_flag' ) ),
		);

		foreach ( get_post_types( array( 'public' => true ) ) as $post_type ) {
			foreach ( $fields as $key => $config ) {
				register_post_meta(
					$post_type,
					$key,
					array(
						'type'              => $config[0],
						'single'            => true,
						'show_in_rest'      => true,
						'sanitize_callback' => $config[1],
						'auth_callback'     => array( __CLASS__, 'can_edit' ),
					)
				);
			}
		}
	}

	/**
	 * Meta auth callback: only users who may edit the post can change its SEO data.
	 *
	 * @param bool   $allowed  Whether the user can add the meta (unused).
	 * @param string $meta_key Meta key (unused).
	 * @param int    $post_id  Post ID.
	 * @return bool
	 */
	public static function can_edit( $allowed, $meta_key, $post_id ) {
		unset( $allowed, $meta_key );
		return current_user_can( 'edit_post', (int) $post_id );
	}

	/**
	 * Sanitizes a custom SEO title.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function sanitize_title( $value ) {
		return mb_substr( Text::squish( sanitize_text_field( (string) $value ) ), 0, self::MAX_TITLE, 'UTF-8' );
	}

	/**
	 * Sanitizes a custom meta description.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function sanitize_description( $value ) {
		return mb_substr( Text::squish( sanitize_textarea_field( (string) $value ) ), 0, self::MAX_DESCRIPTION, 'UTF-8' );
	}

	/**
	 * Sanitizes a canonical URL override. Only http(s) URLs are accepted.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function sanitize_canonical( $value ) {
		return esc_url_raw( trim( (string) $value ), array( 'http', 'https' ) );
	}

	/**
	 * Sanitizes a checkbox flag.
	 *
	 * @param mixed $value Raw value.
	 * @return bool
	 */
	public static function sanitize_flag( $value ) {
		return in_array( $value, array( true, 1, '1', 'on', 'yes', 'true' ), true );
	}

	/**
	 * Reads one SEO field for a post.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key     One of the class constants.
	 * @return string
	 */
	public static function get( $post_id, $key ) {
		$value = get_post_meta( (int) $post_id, $key, true );
		return is_scalar( $value ) ? (string) $value : '';
	}

	/**
	 * Whether the post is excluded from search engines.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public static function is_noindex( $post_id ) {
		return self::sanitize_flag( get_post_meta( (int) $post_id, self::NOINDEX, true ) );
	}
}
