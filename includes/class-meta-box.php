<?php
/**
 * "Smart SEO" meta box on the post editor.
 *
 * @package WP_Smart_SEO
 */

namespace WP_Smart_SEO;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders and saves the per-post SEO fields.
 */
class Meta_Box {

	const NONCE_ACTION = 'wpss_save_meta';
	const NONCE_FIELD  = 'wpss_meta_nonce';

	/**
	 * Resolver used for placeholders and the snippet preview.
	 *
	 * @var Meta_Resolver
	 */
	private $resolver;

	/**
	 * Constructor.
	 *
	 * @param Meta_Resolver $resolver Meta resolver.
	 */
	public function __construct( Meta_Resolver $resolver ) {
		$this->resolver = $resolver;
	}

	/**
	 * Hooks into WordPress.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'add_meta_boxes', array( $this, 'add' ) );
		add_action( 'save_post', array( $this, 'save' ), 10, 2 );
	}

	/**
	 * Adds the meta box to every public post type.
	 *
	 * @return void
	 */
	public function add() {
		foreach ( get_post_types( array( 'public' => true ) ) as $post_type ) {
			if ( 'attachment' === $post_type ) {
				continue;
			}
			add_meta_box(
				'wpss-meta-box',
				__( 'Smart SEO', 'wp-smart-seo' ),
				array( $this, 'render' ),
				$post_type,
				'normal',
				'high'
			);
		}
	}

	/**
	 * Prints the meta box fields.
	 *
	 * @param \WP_Post $post Current post.
	 * @return void
	 */
	public function render( $post ) {
		$title       = Post_Meta::get( $post->ID, Post_Meta::TITLE );
		$description = Post_Meta::get( $post->ID, Post_Meta::DESCRIPTION );
		$canonical   = Post_Meta::get( $post->ID, Post_Meta::CANONICAL );
		$noindex     = Post_Meta::is_noindex( $post->ID );
		$auto_title  = $this->resolver->post_title( $post );
		$auto_desc   = $this->resolver->post_description( $post );
		$permalink   = (string) get_permalink( $post );

		wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD );
		?>
		<div class="wpss-box">
			<div class="wpss-preview" aria-label="<?php esc_attr_e( 'Search result preview', 'wp-smart-seo' ); ?>">
				<span class="wpss-preview__url"><?php echo esc_html( $permalink ); ?></span>
				<span class="wpss-preview__title" data-fallback="<?php echo esc_attr( $auto_title ); ?>"><?php echo esc_html( '' !== $title ? $title : $auto_title ); ?></span>
				<span class="wpss-preview__desc" data-fallback="<?php echo esc_attr( $auto_desc ); ?>"><?php echo esc_html( '' !== $description ? $description : $auto_desc ); ?></span>
			</div>

			<p>
				<label for="wpss-title"><strong><?php esc_html_e( 'SEO title', 'wp-smart-seo' ); ?></strong></label>
				<input type="text" id="wpss-title" name="wpss_title" class="widefat" maxlength="<?php echo esc_attr( (string) Post_Meta::MAX_TITLE ); ?>"
					value="<?php echo esc_attr( $title ); ?>" placeholder="<?php echo esc_attr( $auto_title ); ?>" data-wpss-limit="60" />
				<span class="wpss-counter" aria-live="polite"></span>
				<span class="description"><?php esc_html_e( 'Leave empty to use the title template from Settings → Smart SEO. Aim for 50–60 characters.', 'wp-smart-seo' ); ?></span>
			</p>

			<p>
				<label for="wpss-description"><strong><?php esc_html_e( 'Meta description', 'wp-smart-seo' ); ?></strong></label>
				<textarea id="wpss-description" name="wpss_description" class="widefat" rows="3" maxlength="<?php echo esc_attr( (string) Post_Meta::MAX_DESCRIPTION ); ?>"
					placeholder="<?php echo esc_attr( $auto_desc ); ?>" data-wpss-limit="160"><?php echo esc_textarea( $description ); ?></textarea>
				<span class="wpss-counter" aria-live="polite"></span>
				<span class="description"><?php esc_html_e( 'Leave empty to use the excerpt or the start of the content. Aim for 120–160 characters.', 'wp-smart-seo' ); ?></span>
			</p>

			<p>
				<label for="wpss-canonical"><strong><?php esc_html_e( 'Canonical URL', 'wp-smart-seo' ); ?></strong></label>
				<input type="url" id="wpss-canonical" name="wpss_canonical" class="widefat" value="<?php echo esc_attr( $canonical ); ?>" placeholder="<?php echo esc_attr( $permalink ); ?>" />
				<span class="description"><?php esc_html_e( 'Only set this if the same content lives at another URL.', 'wp-smart-seo' ); ?></span>
			</p>

			<p>
				<label>
					<input type="checkbox" name="wpss_noindex" value="1" <?php checked( $noindex ); ?> />
					<?php esc_html_e( 'Hide this content from search engines (noindex) and the XML sitemap', 'wp-smart-seo' ); ?>
				</label>
			</p>
		</div>
		<?php
	}

	/**
	 * Saves the fields after verifying the nonce, autosave state and capability.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 * @return bool Whether the fields were saved.
	 */
	public function save( $post_id, $post ) {
		if ( ! isset( $_POST[ self::NONCE_FIELD ] ) ) {
			return false;
		}
		$nonce = sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD ] ) );
		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			return false;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) ) {
			return false;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return false;
		}
		unset( $post );

		// Values are sanitized by the Post_Meta sanitizers below.
		// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$title       = isset( $_POST['wpss_title'] ) ? wp_unslash( $_POST['wpss_title'] ) : '';
		$description = isset( $_POST['wpss_description'] ) ? wp_unslash( $_POST['wpss_description'] ) : '';
		$canonical   = isset( $_POST['wpss_canonical'] ) ? wp_unslash( $_POST['wpss_canonical'] ) : '';
		// phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		$values = array(
			Post_Meta::TITLE       => Post_Meta::sanitize_title( $title ),
			Post_Meta::DESCRIPTION => Post_Meta::sanitize_description( $description ),
			Post_Meta::CANONICAL   => Post_Meta::sanitize_canonical( $canonical ),
			Post_Meta::NOINDEX     => ! empty( $_POST['wpss_noindex'] ),
		);

		foreach ( $values as $key => $value ) {
			if ( '' === $value || false === $value ) {
				delete_post_meta( $post_id, $key );
			} else {
				update_post_meta( $post_id, $key, $value );
			}
		}
		return true;
	}
}
