<?php
/**
 * Minimal stand-ins for WordPress core classes used by type checks.
 */

declare(strict_types=1);

// phpcs:disable
if ( ! class_exists( 'WP_Post' ) ) {
	class WP_Post {
		public $ID = 0;
		public $post_title = '';
		public $post_content = '';
		public $post_excerpt = '';
		public $post_type = 'post';
		public $post_author = 1;

		public function __construct( array $data = array() ) {
			foreach ( $data as $key => $value ) {
				$this->$key = $value;
			}
		}
	}
}

if ( ! class_exists( 'WP_Term' ) ) {
	class WP_Term {
		public $term_id = 0;
		public $name = '';
	}
}
