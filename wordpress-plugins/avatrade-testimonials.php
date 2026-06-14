<?php
/**
 * Plugin Name:       AvaTrade Testimonials
 * Description:       Manage client testimonials and expose them over the WordPress REST API for a headless (Next.js) frontend. Registers the avatrade_testimonial post type with custom fields, clean REST output, and seeds demo data on activation.
 * Version:           0.1.0
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
define( 'AVATRADE_TESTIMONIALS_VERSION', '0.1.0' );
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

		add_action( 'init', array( $this, 'register' ) );
	}

	/**
	 * Register the plugin's runtime components.
	 *
	 * Hooked on `init`. This is the seam where the next iteration plugs in:
	 *   - the avatrade_testimonial custom post type
	 *   - its post meta (headline, quote, rating, source) with show_in_rest
	 *   - the admin meta box + save handler
	 *   - the custom REST fields (e.g. featured image object)
	 *
	 * @return void
	 */
	public function register() {
		// Intentionally empty for now — components are added in the next step.
	}

	/**
	 * Runs once on plugin activation.
	 *
	 * The next iteration will register the post type here (so its rewrite
	 * rules exist) and seed demo testimonials before flushing. For now we just
	 * flush so the install starts from a clean, predictable state.
	 *
	 * @return void
	 */
	public function activate() {
		// TODO (next iteration): register the CPT, then seed demo data.
		flush_rewrite_rules();
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

// Fire it up.
Avatrade_Testimonials_Plugin::instance()->run();
