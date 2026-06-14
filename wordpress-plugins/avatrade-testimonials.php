<?php
/**
 * Plugin Name:       AvaTrade Testimonials
 * Description:       Manage client testimonials and expose them over the WordPress REST API for a headless (Next.js) frontend. Registers the avatrade_testimonial post type with custom fields, clean REST output, and seeds demo data on activation.
 * Version:           0.3.1
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Vencislav Venkov
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       avatrade-testimonials
 *
 * @package Avatrade\Testimonials
 */

// Exit if accessed directly — never allow the file to be requested in the browser.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * -------------------------------------------------------------------------
 * Constants
 * -------------------------------------------------------------------------
 * A unique AVATRADE_TESTIMONIALS_ prefix keeps these globals from colliding
 * with WordPress core, the active theme, or any other plugin.
 */
define( 'AVATRADE_TESTIMONIALS_VERSION', '0.3.1' );
define( 'AVATRADE_TESTIMONIALS_FILE', __FILE__ );
define( 'AVATRADE_TESTIMONIALS_DIR', plugin_dir_path( __FILE__ ) );
define( 'AVATRADE_TESTIMONIALS_URL', plugin_dir_url( __FILE__ ) );

/**
 * Main plugin bootstrap.
 *
 * Single entry point for the plugin. As features land, each concern
 * (post type, meta, meta box, REST fields, seeder) gets wired up from the
 * register() method below, keeping this file readable as it grows.
 */
final class Avatrade_Testimonials_Plugin {

	/**
	 * Post type slug. Exactly 20 characters — WordPress's maximum.
	 *
	 * @var string
	 */
	const POST_TYPE = 'avatrade_testimonial';

	/**
	 * Shared singleton instance.
	 *
	 * @var Avatrade_Testimonials_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Retrieve (and lazily create) the shared instance.
	 *
	 * @return Avatrade_Testimonials_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Private constructor — always go through instance().
	 */
	private function __construct() {}

	/**
	 * Boot the plugin: attach lifecycle and runtime hooks.
	 *
	 * Called once at file load. Activation/deactivation hooks must be
	 * registered during the initial plugin load (not inside `init`), which is
	 * why they live here rather than in register().
	 *
	 * @return void
	 */
	public function run() {
		register_activation_hook( AVATRADE_TESTIMONIALS_FILE, array( $this, 'activate' ) );
		register_deactivation_hook( AVATRADE_TESTIMONIALS_FILE, array( $this, 'deactivate' ) );

		add_action( 'after_setup_theme', array( $this, 'add_theme_supports' ) );
		add_action( 'init', array( $this, 'register' ) );

		// Admin editor UI + persistence for the custom fields.
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
		add_action( 'save_post_' . self::POST_TYPE, array( $this, 'save_meta' ), 10, 2 );
		add_filter( 'enter_title_here', array( $this, 'filter_title_placeholder' ), 10, 2 );

		// Custom REST output (resolve the featured image into a usable object).
		add_action( 'rest_api_init', array( $this, 'register_rest_fields' ) );
	}

	/**
	 * Register the plugin's runtime components.
	 *
	 * Hooked on `init`. This is the seam where the next iteration plugs in:
	 *   - the avatrade_testimonial custom post type
	 *   - its post meta (author name, quote, rating, source) with show_in_rest
	 *   - the admin meta box + save handler
	 *   - the custom REST fields (e.g. featured image object)
	 *
	 * @return void
	 */
	public function register() {
		$this->register_post_type();
		$this->register_meta_fields();
		// The featured-image REST field is wired separately on `rest_api_init`
		// (see register_rest_fields); the activation seeder comes next.
	}

	/**
	 * Ensure the active theme exposes featured-image (post thumbnail) support.
	 *
	 * A headless install may run a minimal theme that never opts in, which would
	 * hide the "Author Photo" box in the editor. We enable it globally only when
	 * the theme hasn't already — so we never *narrow* a theme that already
	 * supports thumbnails for all post types.
	 *
	 * @return void
	 */
	public function add_theme_supports() {
		if ( ! current_theme_supports( 'post-thumbnails' ) ) {
			add_theme_support( 'post-thumbnails' );
		}
	}

	/**
	 * Register the avatrade_testimonial custom post type.
	 *
	 * `show_in_rest` is the key flag: it publishes the type at
	 * /wp-json/wp/v2/avatrade_testimonial. `supports` is deliberately limited to
	 * title (the testimonial headline) and thumbnail (the author photo); author
	 * name, quote, rating and source are custom meta, not the rich-text body.
	 *
	 * @return void
	 */
	private function register_post_type() {
		$labels = array(
			'name'                  => _x( 'Testimonials', 'Post type general name', 'avatrade-testimonials' ),
			'singular_name'         => _x( 'Testimonial', 'Post type singular name', 'avatrade-testimonials' ),
			'menu_name'             => _x( 'Testimonials', 'Admin Menu text', 'avatrade-testimonials' ),
			'name_admin_bar'        => _x( 'Testimonial', 'Add New on Toolbar', 'avatrade-testimonials' ),
			'add_new'               => __( 'Add New', 'avatrade-testimonials' ),
			'add_new_item'          => __( 'Add New Testimonial', 'avatrade-testimonials' ),
			'new_item'              => __( 'New Testimonial', 'avatrade-testimonials' ),
			'edit_item'             => __( 'Edit Testimonial', 'avatrade-testimonials' ),
			'view_item'             => __( 'View Testimonial', 'avatrade-testimonials' ),
			'all_items'             => __( 'All Testimonials', 'avatrade-testimonials' ),
			'search_items'          => __( 'Search Testimonials', 'avatrade-testimonials' ),
			'not_found'             => __( 'No testimonials found.', 'avatrade-testimonials' ),
			'not_found_in_trash'    => __( 'No testimonials found in Trash.', 'avatrade-testimonials' ),
			'featured_image'        => __( 'Author Photo', 'avatrade-testimonials' ),
			'set_featured_image'    => __( 'Set author photo', 'avatrade-testimonials' ),
			'remove_featured_image' => __( 'Remove author photo', 'avatrade-testimonials' ),
			'use_featured_image'    => __( 'Use as author photo', 'avatrade-testimonials' ),
			'items_list'            => __( 'Testimonials list', 'avatrade-testimonials' ),
		);

		$args = array(
			'labels'        => $labels,
			'description'   => __( 'Client testimonials exposed via the REST API.', 'avatrade-testimonials' ),
			'public'        => true,           // readable on the front end + anonymously via REST (headless needs this).
			'show_in_menu'  => true,
			'menu_position' => 25,
			'menu_icon'     => 'dashicons-format-quote',
			'supports'      => array( 'title', 'thumbnail', 'custom-fields' ), // 'custom-fields' is required for register_post_meta() to surface under "meta" in REST.
			'has_archive'   => false,          // no WP-rendered archive; the front end is Next.js.
			'rewrite'       => false,          // no pretty permalinks needed.
			'query_var'     => false,
			'show_in_rest'  => true,           // exposes /wp-json/wp/v2/avatrade_testimonial + block editor.
			'rest_base'     => self::POST_TYPE,
		);

		register_post_type( self::POST_TYPE, $args );
	}

	/**
	 * Definition of the custom fields.
	 *
	 * Single source of truth shared by registration (REST + sanitisation), the
	 * meta box renderer, and the save handler — so each field is described once.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function get_meta_fields() {
		return array(
			'avatrade_author_name' => array(
				'label'       => __( 'Author Name', 'avatrade-testimonials' ),
				'description' => __( 'The client’s name, e.g. “Sarah Mitchell”. (The post title holds the headline.)', 'avatrade-testimonials' ),
				'type'        => 'string',
				'control'     => 'text',
				'default'     => '',
				'sanitize'    => 'sanitize_text_field',
			),
			'avatrade_quote'       => array(
				'label'       => __( 'Quote', 'avatrade-testimonials' ),
				'description' => __( 'The full testimonial text.', 'avatrade-testimonials' ),
				'type'        => 'string',
				'control'     => 'textarea',
				'default'     => '',
				'sanitize'    => 'sanitize_textarea_field',
			),
			'avatrade_rating'      => array(
				'label'       => __( 'Star Rating', 'avatrade-testimonials' ),
				'description' => __( 'A whole number from 1 to 5.', 'avatrade-testimonials' ),
				'type'        => 'integer',
				'control'     => 'select',
				'default'     => 5,
				'sanitize'    => array( $this, 'sanitize_rating' ),
			),
			'avatrade_source'      => array(
				'label'       => __( 'Source / Platform', 'avatrade-testimonials' ),
				'description' => __( 'Where the review came from, e.g. Trustpilot.', 'avatrade-testimonials' ),
				'type'        => 'string',
				'control'     => 'text',
				'default'     => '',
				'sanitize'    => 'sanitize_text_field',
			),
		);
	}

	/**
	 * Register each custom field as post meta exposed in the REST API.
	 *
	 * `show_in_rest` surfaces the value under the "meta" object of each
	 * testimonial. Because the post type does not support 'editor', the screen
	 * uses the classic editor (not the block editor), so writes happen solely
	 * through the meta box + save_meta() below — there is no Gutenberg/meta-box
	 * double-save to reconcile.
	 *
	 * @return void
	 */
	private function register_meta_fields() {
		foreach ( $this->get_meta_fields() as $key => $field ) {
			register_post_meta(
				self::POST_TYPE,
				$key,
				array(
					'type'              => $field['type'],
					'description'       => $field['description'],
					'single'            => true,
					'default'           => $field['default'],
					'show_in_rest'      => true,
					'sanitize_callback' => $field['sanitize'],
					'auth_callback'     => array( $this, 'can_edit_testimonials' ),
				)
			);
		}
	}

	/**
	 * Clamp a rating to a whole number within 1–5.
	 *
	 * @param mixed $value Raw value.
	 * @return int
	 */
	public function sanitize_rating( $value ) {
		return min( 5, max( 1, absint( $value ) ) );
	}

	/**
	 * Permission check for editing testimonial meta via REST.
	 *
	 * @return bool
	 */
	public function can_edit_testimonials() {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Add the "Testimonial Details" meta box to the editor screen.
	 *
	 * @return void
	 */
	public function add_meta_box() {
		add_meta_box(
			'avatrade_testimonial_details',
			__( 'Testimonial Details', 'avatrade-testimonials' ),
			array( $this, 'render_meta_box' ),
			self::POST_TYPE,
			'normal',
			'high'
		);

		// 'custom-fields' support (needed for REST meta) also adds WordPress's
		// generic Custom Fields box; hide it since our box already covers these.
		remove_meta_box( 'postcustom', self::POST_TYPE, 'normal' );
	}

	/**
	 * Render the meta box: one labelled control per custom field.
	 *
	 * @param WP_Post $post Current post.
	 * @return void
	 */
	public function render_meta_box( $post ) {
		wp_nonce_field( 'avatrade_save_testimonial', 'avatrade_testimonial_nonce' );

		foreach ( $this->get_meta_fields() as $key => $field ) {
			$id    = esc_attr( $key );
			$value = get_post_meta( $post->ID, $key, true );

			printf(
				'<p><label for="%1$s"><strong>%2$s</strong></label></p>',
				$id,
				esc_html( $field['label'] )
			);

			switch ( $field['control'] ) {
				case 'textarea':
					printf(
						'<textarea id="%1$s" name="%1$s" rows="5" class="widefat">%2$s</textarea>',
						$id,
						esc_textarea( $value )
					);
					break;

				case 'select':
					$current = (int) $value;
					echo '<select id="' . $id . '" name="' . $id . '">';
					for ( $star = 1; $star <= 5; $star++ ) {
						printf(
							'<option value="%1$d"%2$s>%3$s</option>',
							$star,
							selected( $current, $star, false ),
							esc_html( sprintf( _n( '%d star', '%d stars', $star, 'avatrade-testimonials' ), $star ) )
						);
					}
					echo '</select>';
					break;

				default:
					printf(
						'<input type="text" id="%1$s" name="%1$s" value="%2$s" class="widefat" />',
						$id,
						esc_attr( $value )
					);
					break;
			}

			if ( ! empty( $field['description'] ) ) {
				printf( '<p class="description">%s</p>', esc_html( $field['description'] ) );
			}
		}
	}

	/**
	 * Persist the custom fields when a testimonial is saved.
	 *
	 * Guards against CSRF (nonce), autosave, and insufficient permissions, then
	 * sanitises each submitted value with its field-specific callback before
	 * storing it.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object (unused; kept for the hook signature).
	 * @return void
	 */
	public function save_meta( $post_id, $post ) {
		if ( ! isset( $_POST['avatrade_testimonial_nonce'] )
			|| ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['avatrade_testimonial_nonce'] ) ), 'avatrade_save_testimonial' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		foreach ( $this->get_meta_fields() as $key => $field ) {
			if ( ! isset( $_POST[ $key ] ) ) {
				continue;
			}

			$value = call_user_func( $field['sanitize'], wp_unslash( $_POST[ $key ] ) );
			update_post_meta( $post_id, $key, $value );
		}
	}

	/**
	 * Change the title-field placeholder so editors know the post title is the
	 * testimonial headline (not the author's name, which is a meta field).
	 *
	 * @param string  $text Default placeholder.
	 * @param WP_Post $post Current post.
	 * @return string
	 */
	public function filter_title_placeholder( $text, $post ) {
		if ( isset( $post->post_type ) && self::POST_TYPE === $post->post_type ) {
			return __( 'Enter a headline, e.g. “Gives me peace of mind”', 'avatrade-testimonials' );
		}

		return $text;
	}

	/**
	 * Register custom REST output for testimonials.
	 *
	 * Adds `avatrade_featured_image` — the featured image (author photo)
	 * resolved into a ready-to-use object, so the headless frontend doesn't have
	 * to request `?_embed` and dig through `_embedded` just to get a URL.
	 *
	 * @return void
	 */
	public function register_rest_fields() {
		register_rest_field(
			self::POST_TYPE,
			'avatrade_featured_image',
			array(
				'get_callback' => array( $this, 'get_featured_image' ),
				'schema'       => array(
					'description' => __( 'Featured image (author photo), resolved to a usable object, or null.', 'avatrade-testimonials' ),
					'type'        => array( 'object', 'null' ),
					'context'     => array( 'view', 'edit' ),
					'properties'  => array(
						'url'    => array( 'type' => 'string' ),
						'width'  => array( 'type' => 'integer' ),
						'height' => array( 'type' => 'integer' ),
						'alt'    => array( 'type' => 'string' ),
					),
				),
			)
		);
	}

	/**
	 * Resolve a testimonial's featured image for REST output.
	 *
	 * @param array $post REST representation of the post; $post['id'] is the ID.
	 * @return array<string, mixed>|null { url, width, height, alt } or null when unset.
	 */
	public function get_featured_image( $post ) {
		$attachment_id = get_post_thumbnail_id( $post['id'] );
		if ( ! $attachment_id ) {
			return null;
		}

		$image = wp_get_attachment_image_src( $attachment_id, 'full' );
		if ( ! $image ) {
			return null;
		}

		return array(
			'url'    => $image[0],
			'width'  => (int) $image[1],
			'height' => (int) $image[2],
			'alt'    => (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
		);
	}

	/**
	 * Runs once on plugin activation.
	 *
	 * Registers the post type up front (so its rewrite rules exist before the
	 * flush) and seeds demo testimonials on first activation.
	 *
	 * @return void
	 */
	public function activate() {
		$this->register_post_type();

		// Seeding is best-effort: a failure here must NEVER roll back activation
		// (which would deregister the post type and 404 the REST routes).
		// \Throwable catches PHP Errors and Exceptions alike.
		try {
			$this->seed();
		} catch ( \Throwable $e ) {
			error_log( 'AvaTrade Testimonials: demo seeding skipped — ' . $e->getMessage() );
		}

		flush_rewrite_rules();
	}

	/**
	 * The demo testimonials seeded on first activation.
	 *
	 * Six entries, each with a headline (post title), author name, quote,
	 * rating and source. Featured images are paired by position with the files
	 * in assets/seed-images/ (see get_seed_images()).
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function get_seed_data() {
		return array(
			array(
				'headline' => 'Gives me peace of mind',
				'author'   => 'Sarah Mitchell',
				'quote'    => 'The platform is intuitive and the support team is always there when I need them. I finally feel in control of my trades.',
				'rating'   => 5,
				'source'   => 'Trustpilot',
			),
			array(
				'headline' => 'Best broker I’ve used',
				'author'   => 'James Okafor',
				'quote'    => 'Fast execution, transparent fees, and the mobile app is rock solid. Switching to AvaTrade was the best decision for my portfolio.',
				'rating'   => 5,
				'source'   => 'Google',
			),
			array(
				'headline' => 'Perfect for beginners',
				'author'   => 'Elena Rossi',
				'quote'    => 'I started with zero experience. The educational resources and demo account helped me learn without any pressure.',
				'rating'   => 4,
				'source'   => 'Trustpilot',
			),
			array(
				'headline' => 'Reliable and fast',
				'author'   => 'David Chen',
				'quote'    => 'Withdrawals are processed quickly and I’ve never had an issue with slippage, even during volatile markets.',
				'rating'   => 5,
				'source'   => 'App Store',
			),
			array(
				'headline' => 'Customer service that stands out',
				'author'   => 'Amara Nwosu',
				'quote'    => 'Whenever I have a question, a real person answers within minutes. That level of care is rare these days.',
				'rating'   => 5,
				'source'   => 'Trustpilot',
			),
			array(
				'headline' => 'A platform I trust',
				'author'   => 'Thomas Müller',
				'quote'    => 'Regulated, secure, and packed with the tools I need for technical analysis. Highly recommended.',
				'rating'   => 4,
				'source'   => 'Google',
			),
		);
	}

	/**
	 * Seed the demo testimonials — once.
	 *
	 * Guarded by the avatrade_testimonials_seeded option so repeat activations
	 * never duplicate the data. Each entry is published with its meta and, by
	 * position, paired with a bundled image from assets/seed-images/.
	 *
	 * @return void
	 */
	private function seed() {
		if ( get_option( 'avatrade_testimonials_seeded' ) ) {
			return;
		}

		// Media helpers aren't loaded during activation — pull them in (guarded).
		foreach ( array( 'image.php', 'file.php', 'media.php' ) as $include ) {
			$path = ABSPATH . 'wp-admin/includes/' . $include;
			if ( file_exists( $path ) ) {
				require_once $path;
			}
		}

		$images = $this->get_seed_images();

		foreach ( array_values( $this->get_seed_data() ) as $index => $item ) {
			$post_id = wp_insert_post(
				array(
					'post_type'   => self::POST_TYPE,
					'post_status' => 'publish',
					'post_title'  => $item['headline'],
				),
				true
			);

			if ( is_wp_error( $post_id ) || ! $post_id ) {
				continue;
			}

			update_post_meta( $post_id, 'avatrade_author_name', $item['author'] );
			update_post_meta( $post_id, 'avatrade_quote', $item['quote'] );
			update_post_meta( $post_id, 'avatrade_rating', (int) $item['rating'] );
			update_post_meta( $post_id, 'avatrade_source', $item['source'] );

			if ( isset( $images[ $index ] ) ) {
				$this->seed_featured_image( $post_id, $images[ $index ], $item['author'] );
			}
		}

		update_option( 'avatrade_testimonials_seeded', AVATRADE_TESTIMONIALS_VERSION );
	}

	/**
	 * Return the bundled seed images, sorted, as absolute file paths.
	 *
	 * Non-image files in the directory (e.g. the index.php guard) are ignored.
	 * Position in this list maps to position in get_seed_data().
	 *
	 * @return array<int, string>
	 */
	private function get_seed_images() {
		$found = glob( AVATRADE_TESTIMONIALS_DIR . 'assets/seed-images/*' );

		if ( empty( $found ) ) {
			return array();
		}

		$images = array_filter(
			$found,
			static function ( $path ) {
				$ext = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
				return in_array( $ext, array( 'jpg', 'jpeg', 'png', 'gif', 'webp' ), true );
			}
		);

		sort( $images );

		return array_values( $images );
	}

	/**
	 * Copy a bundled image into the media library and set it as the featured
	 * image for a testimonial.
	 *
	 * Sub-size generation degrades gracefully if no image editor (e.g. GD) is
	 * available in the runtime — the original full-size image is still stored
	 * and returned via the REST field.
	 *
	 * @param int    $post_id     Testimonial post ID.
	 * @param string $source_path Absolute path to the bundled image.
	 * @param string $alt         Alt text (the author's name).
	 * @return void
	 */
	private function seed_featured_image( $post_id, $source_path, $alt ) {
		// Bail quietly if the media stack isn't available in this runtime.
		if ( ! function_exists( 'wp_insert_attachment' ) || ! function_exists( 'wp_generate_attachment_metadata' ) ) {
			return;
		}

		$uploads = wp_upload_dir();
		if ( ! empty( $uploads['error'] ) ) {
			return;
		}

		$filename = wp_unique_filename( $uploads['path'], basename( $source_path ) );
		$dest     = trailingslashit( $uploads['path'] ) . $filename;

		if ( ! copy( $source_path, $dest ) ) {
			return;
		}

		$filetype   = wp_check_filetype( $filename, null );
		$attachment = array(
			'post_mime_type' => $filetype['type'],
			'post_title'     => sanitize_file_name( pathinfo( $filename, PATHINFO_FILENAME ) ),
			'post_content'   => '',
			'post_status'    => 'inherit',
		);

		$attach_id = wp_insert_attachment( $attachment, $dest, $post_id );
		if ( is_wp_error( $attach_id ) || ! $attach_id ) {
			return;
		}

		$metadata = wp_generate_attachment_metadata( $attach_id, $dest );
		if ( ! empty( $metadata ) ) {
			wp_update_attachment_metadata( $attach_id, $metadata );
		}

		update_post_meta( $attach_id, '_wp_attachment_image_alt', $alt );
		set_post_thumbnail( $post_id, $attach_id );
	}

	/**
	 * Runs once on plugin deactivation.
	 *
	 * Flush rewrite rules so the deactivated post type's routes are removed.
	 * We deliberately do NOT delete testimonials or options here — destroying
	 * user content belongs in uninstall.php, not deactivation.
	 *
	 * @return void
	 */
	public function deactivate() {
		flush_rewrite_rules();
	}
}

Avatrade_Testimonials_Plugin::instance()->run();
