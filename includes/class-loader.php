<?php
/**
 * The main loader class.
 *
 * Loads and initializes all plugin components.
 *
 * @package Toptour_Ref
 * @version 0.1.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main plugin loader class.
 */
class Toptour_Ref_Loader {

	/**
	 * Run the plugin loader.
	 *
	 * @return void
	 */
	public static function run() {
		self::load_includes();
		self::init_hooks();
	}

	/**
	 * Load plugin include files.
	 *
	 * @return void
	 */
	private static function load_includes() {
		require_once TOPTOUR_REF_PLUGIN_DIR . 'includes/class-installer.php';
		require_once TOPTOUR_REF_PLUGIN_DIR . 'includes/class-capabilities.php';
		require_once TOPTOUR_REF_PLUGIN_DIR . 'includes/class-labels.php';
		require_once TOPTOUR_REF_PLUGIN_DIR . 'includes/class-offers.php';
		require_once TOPTOUR_REF_PLUGIN_DIR . 'includes/class-collection-tasks.php';
		require_once TOPTOUR_REF_PLUGIN_DIR . 'includes/class-task-runs.php';
		require_once TOPTOUR_REF_PLUGIN_DIR . 'includes/class-task-events.php';
		require_once TOPTOUR_REF_PLUGIN_DIR . 'includes/class-offer-snapshots.php';
		require_once TOPTOUR_REF_PLUGIN_DIR . 'includes/class-search-provider.php';
		require_once TOPTOUR_REF_PLUGIN_DIR . 'includes/class-task-processor.php';
		require_once TOPTOUR_REF_PLUGIN_DIR . 'includes/class-data-intake-router.php';
		require_once TOPTOUR_REF_PLUGIN_DIR . 'includes/class-facilities.php';
		require_once TOPTOUR_REF_PLUGIN_DIR . 'includes/class-destinations.php';
		require_once TOPTOUR_REF_PLUGIN_DIR . 'includes/class-facility-destinations.php';
		require_once TOPTOUR_REF_PLUGIN_DIR . 'includes/class-contacts.php';
		require_once TOPTOUR_REF_PLUGIN_DIR . 'includes/class-resident-profiles.php';
		require_once TOPTOUR_REF_PLUGIN_DIR . 'includes/class-interests.php';
		require_once TOPTOUR_REF_PLUGIN_DIR . 'includes/class-contact-interests.php';
		require_once TOPTOUR_REF_PLUGIN_DIR . 'includes/class-contact-influence.php';
		require_once TOPTOUR_REF_PLUGIN_DIR . 'includes/class-contact-relationships.php';
		require_once TOPTOUR_REF_PLUGIN_DIR . 'includes/class-points-of-interest.php';
		require_once TOPTOUR_REF_PLUGIN_DIR . 'includes/class-reference-sources.php';
		require_once TOPTOUR_REF_PLUGIN_DIR . 'includes/class-discovery-runs.php';
		require_once TOPTOUR_REF_PLUGIN_DIR . 'includes/class-discovery-candidates.php';
		require_once TOPTOUR_REF_PLUGIN_DIR . 'includes/class-discovery-provider.php';
		require_once TOPTOUR_REF_PLUGIN_DIR . 'includes/class-collection-task-resolver.php';
		require_once TOPTOUR_REF_PLUGIN_DIR . 'includes/class-mail-templates.php';
		require_once TOPTOUR_REF_PLUGIN_DIR . 'includes/class-mail-queue.php';
		require_once TOPTOUR_REF_PLUGIN_DIR . 'includes/class-findings.php';
		require_once TOPTOUR_REF_PLUGIN_DIR . 'includes/class-photo-evidence.php';
		require_once TOPTOUR_REF_PLUGIN_DIR . 'includes/class-admin.php';
		require_once TOPTOUR_REF_PLUGIN_DIR . 'includes/class-rest-api.php';
	}

	/**
	 * Initialize plugin hooks and components.
	 *
	 * @return void
	 */
	private static function init_hooks() {
		// Initialize capabilities on WordPress setup.
		add_action( 'wp_loaded', array( 'Toptour_Ref_Capabilities', 'register' ) );

		// Database migration/upgrade check.
		add_action( 'admin_init', array( 'Toptour_Ref_Installer', 'maybe_upgrade' ) );

		// Initialize admin interface if in admin area.
		if ( is_admin() ) {
			add_action( 'admin_menu', array( 'Toptour_Ref_Admin', 'register_menu' ) );
			add_action( 'admin_enqueue_scripts', array( 'Toptour_Ref_Admin', 'enqueue_admin_assets' ) );
		}

		// Register REST API routes.
		add_action( 'rest_api_init', array( 'Toptour_Ref_REST_API', 'register_routes' ) );

		// Scheduler hook for controlled automatic processing.
		add_action( 'toptour_ref_process_collection_tasks', array( 'Toptour_Ref_Task_Processor', 'process_scheduled_tasks' ) );
	}
}
