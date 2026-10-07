<?php
/**
 * JSON-LD structured data builders (schema.org).
 *
 * @package WP_Smart_SEO
 */

namespace WP_Smart_SEO;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pure functions that return schema.org nodes as PHP arrays.
 */
final class Schema {

	/**
	 * Google truncates Article headlines longer than this.
	 */
	const HEADLINE_LENGTH = 110;

	/**
	 * Organization or Person node that represents the site owner.
	 *
	 * @param array<string,mixed> $args Keys: type (Organization|Person), name, url, logo, same_as.
	 * @return array<string,mixed>
	 */
	public static function publisher( array $args ) {
		$type = isset( $args['type'] ) && 'Person' === $args['type'] ? 'Person' : 'Organization';
		$url  = isset( $args['url'] ) ? (string) $args['url'] : '';
		$node = array(
			'@type' => $type,
			'@id'   => self::id( $url, strtolower( $type ) ),
			'name'  => isset( $args['name'] ) ? (string) $args['name'] : '',
			'url'   => $url,
		);
		if ( ! empty( $args['logo'] ) ) {
			$image = array(
				'@type' => 'ImageObject',
				'url'   => (string) $args['logo'],
			);
			$node[ 'Person' === $type ? 'image' : 'logo' ] = $image;
		}
		if ( ! empty( $args['same_as'] ) && is_array( $args['same_as'] ) ) {
			$node['sameAs'] = array_values( array_map( 'strval', $args['same_as'] ) );
		}
		return $node;
	}

	/**
	 * WebSite node with a sitelinks search box action.
	 *
	 * @param string $name         Site name.
	 * @param string $url          Home URL.
	 * @param string $description  Site description.
	 * @param string $publisher_id @id of the publisher node.
	 * @return array<string,mixed>
	 */
	public static function website( $name, $url, $description, $publisher_id ) {
		$node = array(
			'@type'           => 'WebSite',
			'@id'             => self::id( $url, 'website' ),
			'url'             => $url,
			'name'            => $name,
			'publisher'       => array( '@id' => $publisher_id ),
			'potentialAction' => array(
				'@type'       => 'SearchAction',
				'target'      => array(
					'@type'       => 'EntryPoint',
					'urlTemplate' => rtrim( $url, '/' ) . '/?s={search_term_string}',
				),
				'query-input' => 'required name=search_term_string',
			),
		);
		if ( '' !== $description ) {
			$node['description'] = $description;
		}
		return $node;
	}

	/**
	 * Article / BlogPosting node.
	 *
	 * @param array<string,mixed> $post Keys: type, url, headline, description, published,
	 *                                  modified, author_name, author_url, image, website_id,
	 *                                  publisher_id, section, keywords.
	 * @return array<string,mixed>
	 */
	public static function article( array $post ) {
		$url      = isset( $post['url'] ) ? (string) $post['url'] : '';
		$headline = Text::truncate( isset( $post['headline'] ) ? (string) $post['headline'] : '', self::HEADLINE_LENGTH );
		$type     = isset( $post['type'] ) && in_array( $post['type'], array( 'Article', 'BlogPosting', 'NewsArticle' ), true )
			? $post['type']
			: 'Article';

		$node = array(
			'@type'            => $type,
			'@id'              => self::id( $url, 'article' ),
			'headline'         => $headline,
			'url'              => $url,
			'mainEntityOfPage' => array( '@id' => $url ),
			'datePublished'    => isset( $post['published'] ) ? (string) $post['published'] : '',
			'dateModified'     => isset( $post['modified'] ) ? (string) $post['modified'] : '',
		);
		if ( ! empty( $post['description'] ) ) {
			$node['description'] = (string) $post['description'];
		}
		if ( ! empty( $post['author_name'] ) ) {
			$node['author'] = array_filter(
				array(
					'@type' => 'Person',
					'name'  => (string) $post['author_name'],
					'url'   => isset( $post['author_url'] ) ? (string) $post['author_url'] : '',
				)
			);
		}
		if ( ! empty( $post['image'] ) ) {
			$node['image'] = array(
				'@type' => 'ImageObject',
				'url'   => (string) $post['image'],
			);
		}
		if ( ! empty( $post['section'] ) ) {
			$node['articleSection'] = (string) $post['section'];
		}
		if ( ! empty( $post['keywords'] ) && is_array( $post['keywords'] ) ) {
			$node['keywords'] = implode( ', ', array_map( 'strval', $post['keywords'] ) );
		}
		if ( ! empty( $post['website_id'] ) ) {
			$node['isPartOf'] = array( '@id' => (string) $post['website_id'] );
		}
		if ( ! empty( $post['publisher_id'] ) ) {
			$node['publisher'] = array( '@id' => (string) $post['publisher_id'] );
		}
		return array_filter(
			$node,
			static function ( $value ) {
				return '' !== $value;
			}
		);
	}

	/**
	 * Wraps nodes into a single @graph document.
	 *
	 * @param array<int, array<string,mixed>> $nodes Schema nodes.
	 * @return array<string,mixed>
	 */
	public static function graph( array $nodes ) {
		return array(
			'@context' => 'https://schema.org',
			'@graph'   => array_values( array_filter( $nodes ) ),
		);
	}

	/**
	 * Encodes a graph for a <script type="application/ld+json"> block.
	 *
	 * `<`, `>` and `&` are hex-escaped so content such as "</script>" in a post
	 * title can never break out of the script element.
	 *
	 * @param array<string,mixed> $graph Graph document.
	 * @return string
	 */
	public static function encode( array $graph ) {
		$json = wp_json_encode( $graph, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP );
		return false === $json ? '' : $json;
	}

	/**
	 * Builds a stable node identifier such as https://example.com/#organization.
	 *
	 * @param string $url      Base URL.
	 * @param string $fragment Fragment name.
	 * @return string
	 */
	public static function id( $url, $fragment ) {
		$base = strtok( (string) $url, '#' );
		return ( false === $base ? '' : $base ) . '#' . $fragment;
	}
}
