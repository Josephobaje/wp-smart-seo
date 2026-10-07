<?php
/**
 * Settings → Smart SEO admin page (Settings API).
 *
 * @package WP_Smart_SEO
 */

namespace WP_Smart_SEO;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and renders the plugin settings page.
 */
class Settings_Page {

	const SLUG  = 'wp-smart-seo';
	const GROUP = 'wpss';

	/**
	 * Settings instance.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param Settings $settings Plugin settings.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Hooks into WordPress.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'admin_menu', array( $this, 'add_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'update_option_' . Settings::OPTION, array( $this, 'after_update' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( WPSS_FILE ), array( $this, 'action_links' ) );
	}

	/**
	 * Adds the page under Settings.
	 *
	 * @return void
	 */
	public function add_page() {
		add_options_page(
			__( 'Smart SEO Settings', 'wp-smart-seo' ),
			__( 'Smart SEO', 'wp-smart-seo' ),
			'manage_options',
			self::SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * Adds a "Settings" link on the Plugins screen.
	 *
	 * @param string[] $links Action links.
	 * @return string[]
	 */
	public function action_links( $links ) {
		$url = admin_url( 'options-general.php?page=' . self::SLUG );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'wp-smart-seo' ) . '</a>' );
		return $links;
	}

	/**
	 * Clears caches and refreshes rewrite rules after saving.
	 *
	 * @return void
	 */
	public function after_update() {
		$this->settings->flush();
		Sitemap::add_rewrite_rules();
		flush_rewrite_rules( false );
	}

	/**
	 * Registers the option, sections and fields.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			self::GROUP,
			Settings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( Settings::class, 'sanitize' ),
				'default'           => Settings::defaults(),
			)
		);

		$sections = array(
			'titles'   => __( 'Titles & descriptions', 'wp-smart-seo' ),
			'social'   => __( 'Social sharing', 'wp-smart-seo' ),
			'schema'   => __( 'Structured data (JSON-LD)', 'wp-smart-seo' ),
			'indexing' => __( 'Sitemap & indexing', 'wp-smart-seo' ),
		);
		foreach ( $sections as $id => $title ) {
			add_settings_section( 'wpss_' . $id, $title, '__return_false', self::SLUG );
		}

		$fields = array(
			array( 'separator', __( 'Title separator', 'wp-smart-seo' ), 'titles', 'separator' ),
			array(
				'title_template',
				__( 'Post title template', 'wp-smart-seo' ),
				'titles',
				'text',
				/* translators: %s: list of template variables. */
				sprintf( __( 'Variables: %s', 'wp-smart-seo' ), '%title%, %sitename%, %tagline%, %sep%, %page%' ),
			),
			array( 'home_title_template', __( 'Home title template', 'wp-smart-seo' ), 'titles', 'text' ),
			array( 'home_description', __( 'Home meta description', 'wp-smart-seo' ), 'titles', 'textarea', __( 'Falls back to the site tagline.', 'wp-smart-seo' ) ),
			array( 'default_og_image', __( 'Default share image URL', 'wp-smart-seo' ), 'social', 'url', __( 'Used when a post has no featured image. 1200 × 630 px recommended.', 'wp-smart-seo' ) ),
			array( 'twitter_site', __( 'X (Twitter) username', 'wp-smart-seo' ), 'social', 'text', __( 'For example @yourbrand.', 'wp-smart-seo' ) ),
			array( 'schema_type', __( 'Site represents', 'wp-smart-seo' ), 'schema', 'schema_type' ),
			array( 'schema_name', __( 'Organization or person name', 'wp-smart-seo' ), 'schema', 'text', __( 'Defaults to the site title.', 'wp-smart-seo' ) ),
			array( 'schema_logo', __( 'Logo / photo URL', 'wp-smart-seo' ), 'schema', 'url' ),
			array( 'schema_same_as', __( 'Social profiles', 'wp-smart-seo' ), 'schema', 'same_as', __( 'One URL per line (LinkedIn, GitHub, Facebook, ...).', 'wp-smart-seo' ) ),
			array( 'sitemap_enabled', __( 'XML sitemap', 'wp-smart-seo' ), 'indexing', 'checkbox', __( 'Enable the XML sitemap', 'wp-smart-seo' ) ),
			array( 'sitemap_post_types', __( 'Include post types', 'wp-smart-seo' ), 'indexing', 'post_types' ),
			array( 'disable_core_sitemap', __( 'Core sitemap', 'wp-smart-seo' ), 'indexing', 'checkbox', __( 'Disable the built-in wp-sitemap.xml to avoid duplicates', 'wp-smart-seo' ) ),
			array( 'noindex_search', __( 'Search results', 'wp-smart-seo' ), 'indexing', 'checkbox', __( 'Noindex internal search result pages', 'wp-smart-seo' ) ),
			array( 'noindex_date_archives', __( 'Date archives', 'wp-smart-seo' ), 'indexing', 'checkbox', __( 'Noindex date archives (thin, duplicate content)', 'wp-smart-seo' ) ),
		);
		foreach ( $fields as $field ) {
			add_settings_field(
				'wpss_' . $field[0],
				$field[1],
				array( $this, 'render_field' ),
				self::SLUG,
				'wpss_' . $field[2],
				array(
					'key'       => $field[0],
					'type'      => $field[3],
					'help'      => isset( $field[4] ) ? $field[4] : '',
					'label_for' => in_array( $field[3], array( 'checkbox', 'post_types' ), true ) ? null : 'wpss-' . $field[0],
				)
			);
		}
	}

	/**
	 * Renders a single field.
	 *
	 * @param array<string,string|null> $args Field arguments.
	 * @return void
	 */
	public function render_field( $args ) {
		$key   = (string) $args['key'];
		$type  = (string) $args['type'];
		$help  = (string) $args['help'];
		$value = $this->settings->get( $key );
		$name  = Settings::OPTION . '[' . $key . ']';
		$id    = 'wpss-' . $key;

		switch ( $type ) {
			case 'separator':
				echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '">';
				foreach ( Settings::SEPARATORS as $separator ) {
					echo '<option value="' . esc_attr( $separator ) . '" ' . selected( $value, $separator, false ) . '>' . esc_html( $separator ) . '</option>';
				}
				echo '</select>';
				break;
			case 'schema_type':
				echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '">';
				echo '<option value="Organization" ' . selected( $value, 'Organization', false ) . '>' . esc_html__( 'An organization or business', 'wp-smart-seo' ) . '</option>';
				echo '<option value="Person" ' . selected( $value, 'Person', false ) . '>' . esc_html__( 'A person', 'wp-smart-seo' ) . '</option>';
				echo '</select>';
				break;
			case 'textarea':
				echo '<textarea id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" rows="3" class="large-text">' . esc_textarea( (string) $value ) . '</textarea>';
				break;
			case 'same_as':
				echo '<textarea id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" rows="4" class="large-text code">' . esc_textarea( implode( "\n", (array) $value ) ) . '</textarea>';
				break;
			case 'checkbox':
				echo '<label><input type="checkbox" name="' . esc_attr( $name ) . '" value="1" ' . checked( (bool) $value, true, false ) . ' /> ' . esc_html( $help ) . '</label>';
				$help = '';
				break;
			case 'post_types':
				foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $post_type ) {
					if ( 'attachment' === $post_type->name ) {
						continue;
					}
					echo '<label style="margin-right:16px"><input type="checkbox" name="' . esc_attr( $name ) . '[]" value="' . esc_attr( $post_type->name ) . '" ' . checked( in_array( $post_type->name, (array) $value, true ), true, false ) . ' /> ' . esc_html( $post_type->labels->name ) . '</label>';
				}
				break;
			default:
				echo '<input type="' . ( 'url' === $type ? 'url' : 'text' ) . '" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $value ) . '" class="regular-text" />';
		}

		if ( '' !== $help ) {
			echo '<p class="description">' . esc_html( $help ) . '</p>';
		}
	}

	/**
	 * Renders the settings page.
	 *
	 * @return void
	 */
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap wpss-settings">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<?php if ( $this->settings->get( 'sitemap_enabled' ) ) : ?>
				<p>
					<?php esc_html_e( 'Your XML sitemap:', 'wp-smart-seo' ); ?>
					<a href="<?php echo esc_url( Sitemap::index_url() ); ?>" target="_blank" rel="noopener"><?php echo esc_html( Sitemap::index_url() ); ?></a>
				</p>
			<?php endif; ?>
			<form action="options.php" method="post">
				<?php
				settings_fields( self::GROUP );
				do_settings_sections( self::SLUG );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}
}
