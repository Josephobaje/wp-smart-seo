<?php
/**
 * Plugin settings: defaults, sanitization and access.
 *
 * @package WP_Smart_SEO
 */

namespace WP_Smart_SEO;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wraps the `wpss_settings` option.
 */
class Settings {

	const OPTION = 'wpss_settings';

	/**
	 * Allowed title separators.
	 *
	 * @var string[]
	 */
	const SEPARATORS = array( '-', '–', '—', '|', '•', '·', '»' );

	/**
	 * Cached, sanitized settings.
	 *
	 * @var array<string, mixed>|null
	 */
	private $values = null;

	/**
	 * Default settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults() {
		return array(
			'separator'             => '–',
			'title_template'        => '%title% %sep% %sitename%',
			'home_title_template'   => '%sitename% %sep% %tagline%',
			'home_description'      => '',
			'default_og_image'      => '',
			'twitter_site'          => '',
			'schema_type'           => 'Organization',
			'schema_name'           => '',
			'schema_logo'           => '',
			'schema_same_as'        => array(),
			'sitemap_enabled'       => true,
			'sitemap_post_types'    => array( 'post', 'page' ),
			'disable_core_sitemap'  => true,
			'noindex_search'        => true,
			'noindex_date_archives' => true,
		);
	}

	/**
	 * Returns every setting, merged with defaults.
	 *
	 * @return array<string, mixed>
	 */
	public function all() {
		if ( null === $this->values ) {
			$stored       = get_option( self::OPTION, array() );
			$this->values = array_merge( self::defaults(), is_array( $stored ) ? $stored : array() );
		}
		return $this->values;
	}

	/**
	 * Returns one setting.
	 *
	 * @param string $key Setting name.
	 * @return mixed
	 */
	public function get( $key ) {
		$all = $this->all();
		return array_key_exists( $key, $all ) ? $all[ $key ] : null;
	}

	/**
	 * Clears the in-memory cache (after the option changes).
	 *
	 * @return void
	 */
	public function flush() {
		$this->values = null;
	}

	/**
	 * Sanitizes the settings array submitted from the settings page.
	 *
	 * Unknown keys are dropped, every value is validated, and missing checkboxes
	 * are treated as unchecked.
	 *
	 * @param mixed $input Raw option value.
	 * @return array<string, mixed>
	 */
	public static function sanitize( $input ) {
		$input    = is_array( $input ) ? $input : array();
		$defaults = self::defaults();
		$clean    = array();

		$separator          = isset( $input['separator'] ) ? (string) $input['separator'] : $defaults['separator'];
		$clean['separator'] = in_array( $separator, self::SEPARATORS, true ) ? $separator : $defaults['separator'];

		foreach ( array( 'title_template', 'home_title_template' ) as $key ) {
			$value         = isset( $input[ $key ] ) ? Text::squish( sanitize_text_field( (string) $input[ $key ] ) ) : '';
			$clean[ $key ] = '' !== $value ? $value : $defaults[ $key ];
		}

		$clean['home_description'] = isset( $input['home_description'] )
			? Text::squish( sanitize_textarea_field( (string) $input['home_description'] ) )
			: '';

		foreach ( array( 'default_og_image', 'schema_logo' ) as $key ) {
			$clean[ $key ] = isset( $input[ $key ] ) ? esc_url_raw( trim( (string) $input[ $key ] ), array( 'http', 'https' ) ) : '';
		}

		$clean['twitter_site'] = isset( $input['twitter_site'] ) ? self::sanitize_twitter_handle( (string) $input['twitter_site'] ) : '';

		$type                 = isset( $input['schema_type'] ) ? (string) $input['schema_type'] : '';
		$clean['schema_type'] = in_array( $type, array( 'Organization', 'Person' ), true ) ? $type : $defaults['schema_type'];
		$clean['schema_name'] = isset( $input['schema_name'] ) ? Text::squish( sanitize_text_field( (string) $input['schema_name'] ) ) : '';

		$same_as = isset( $input['schema_same_as'] ) ? $input['schema_same_as'] : array();
		if ( is_string( $same_as ) ) {
			$same_as = preg_split( '/[\r\n]+/', $same_as );
		}
		$clean['schema_same_as'] = array_values(
			array_unique(
				array_filter(
					array_map(
						static function ( $url ) {
							return esc_url_raw( trim( (string) $url ), array( 'https', 'http' ) );
						},
						is_array( $same_as ) ? $same_as : array()
					)
				)
			)
		);

		foreach ( array( 'sitemap_enabled', 'disable_core_sitemap', 'noindex_search', 'noindex_date_archives' ) as $key ) {
			$clean[ $key ] = ! empty( $input[ $key ] );
		}

		$types                       = isset( $input['sitemap_post_types'] ) && is_array( $input['sitemap_post_types'] ) ? $input['sitemap_post_types'] : array();
		$public                      = get_post_types( array( 'public' => true ) );
		$clean['sitemap_post_types'] = array_values(
			array_filter(
				array_map( 'sanitize_key', $types ),
				static function ( $type ) use ( $public ) {
					return in_array( $type, (array) $public, true ) && 'attachment' !== $type;
				}
			)
		);

		return $clean;
	}

	/**
	 * Normalises a Twitter/X handle to "@handle", or returns '' when invalid.
	 *
	 * @param string $handle Raw handle, URL or @handle.
	 * @return string
	 */
	public static function sanitize_twitter_handle( $handle ) {
		$handle = trim( $handle );
		$handle = (string) preg_replace( '#^https?://(www\.)?(twitter|x)\.com/#i', '', $handle );
		$handle = ltrim( $handle, '@' );
		$handle = (string) strtok( $handle, '/?#' );
		return preg_match( '/^[A-Za-z0-9_]{1,15}$/', $handle ) ? '@' . $handle : '';
	}
}
