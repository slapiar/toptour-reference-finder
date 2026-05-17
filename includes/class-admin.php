<?php
/**
 * Plugin admin interface class.
 *
 * Handles admin menu registration and page rendering.
 *
 * @package Toptour_Ref
 * @version 0.2.14
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin interface class.
 */
class Toptour_Ref_Admin {

	/**
	 * Register admin menu and submenus.
	 *
	 * @return void
	 */
	public static function register_menu() {
		// Check capability.
		if ( ! Toptour_Ref_Capabilities::user_can_manage_references() ) {
			return;
		}

		// Add main menu.
		$main_menu_hook = add_menu_page(
			esc_html__( 'TOPTOUR References', 'toptour-reference-finder' ),
			esc_html__( 'TOPTOUR References', 'toptour-reference-finder' ),
			'manage_toptour_references',
			'toptour-references',
			array( __CLASS__, 'render_dashboard_page' ),
			'dashicons-search',
			90
		);

		// Add submenu pages.
		$submenus = array(
			array(
				'slug'       => 'toptour-references',
				'title'      => esc_html__( 'Dashboard', 'toptour-reference-finder' ),
				'menu_title' => esc_html__( 'Dashboard', 'toptour-reference-finder' ),
				'callback'   => 'render_dashboard_page',
			),
			array(
				'slug'       => 'toptour-references-facilities',
				'title'      => esc_html__( 'Zariadenia', 'toptour-reference-finder' ),
				'menu_title' => esc_html__( 'Zariadenia', 'toptour-reference-finder' ),
				'callback'   => 'render_facilities_page',
			),
			array(
				'slug'       => 'toptour-references-destinations',
				'title'      => esc_html__( 'Destinácie', 'toptour-reference-finder' ),
				'menu_title' => esc_html__( 'Destinácie', 'toptour-reference-finder' ),
				'callback'   => 'render_destinations_page',
			),
			array(
				'slug'       => 'toptour-references-points-of-interest',
				'title'      => esc_html__( 'Body záujmu', 'toptour-reference-finder' ),
				'menu_title' => esc_html__( 'Body záujmu', 'toptour-reference-finder' ),
				'callback'   => 'render_points_of_interest_page',
			),
			array(
				'slug'       => 'toptour-references-contacts',
				'title'      => esc_html__( 'Kontakty', 'toptour-reference-finder' ),
				'menu_title' => esc_html__( 'Kontakty', 'toptour-reference-finder' ),
				'callback'   => 'render_contacts_page',
			),
			array(
				'slug'       => 'toptour-references-interests',
				'title'      => esc_html__( 'Záujmy', 'toptour-reference-finder' ),
				'menu_title' => esc_html__( 'Záujmy', 'toptour-reference-finder' ),
				'callback'   => 'render_interests_page',
			),
			array(
				'slug'       => 'toptour-references-offers',
				'title'      => esc_html__( 'Ponuky', 'toptour-reference-finder' ),
				'menu_title' => esc_html__( 'Ponuky', 'toptour-reference-finder' ),
				'callback'   => 'render_offers_page',
			),
			array(
				'slug'       => 'toptour-references-sources',
				'title'      => esc_html__( 'Referenčné zdroje', 'toptour-reference-finder' ),
				'menu_title' => esc_html__( 'Referenčné zdroje', 'toptour-reference-finder' ),
				'callback'   => 'render_sources_page',
			),
			array(
				'slug'       => 'toptour-references-findings',
				'title'      => esc_html__( 'Zistenia', 'toptour-reference-finder' ),
				'menu_title' => esc_html__( 'Zistenia', 'toptour-reference-finder' ),
				'callback'   => 'render_findings_page',
			),
			array(
				'slug'       => 'toptour-references-photo-evidence',
				'title'      => esc_html__( 'Fotodôkazy', 'toptour-reference-finder' ),
				'menu_title' => esc_html__( 'Fotodôkazy', 'toptour-reference-finder' ),
				'callback'   => 'render_photo_evidence_page',
			),
			array(
				'slug'       => 'toptour-references-collection',
				'title'      => esc_html__( 'Zber referencií', 'toptour-reference-finder' ),
				'menu_title' => esc_html__( 'Zber referencií', 'toptour-reference-finder' ),
				'callback'   => 'render_collection_page',
			),
			array(
				'slug'       => 'toptour-references-settings',
				'title'      => esc_html__( 'Nastavenia', 'toptour-reference-finder' ),
				'menu_title' => esc_html__( 'Nastavenia', 'toptour-reference-finder' ),
				'callback'   => 'render_settings_page',
			),
		);

		// Register all submenus.
		foreach ( $submenus as $submenu ) {
			add_submenu_page(
				'toptour-references',
				$submenu['title'],
				$submenu['menu_title'],
				'manage_toptour_references',
				$submenu['slug'],
				array( __CLASS__, $submenu['callback'] )
			);
		}
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @return void
	 */
	public static function enqueue_admin_assets() {
		// Only enqueue on TOPTOUR References pages.
		$screen = get_current_screen();
		if ( ! $screen || strpos( $screen->id, 'toptour-reference' ) === false ) {
			return;
		}

		// Enqueue admin styles.
		wp_enqueue_style(
			'toptour-ref-admin',
			TOPTOUR_REF_PLUGIN_URL . 'admin/assets/admin.css',
			array(),
			TOPTOUR_REF_VERSION
		);

		// Enqueue tracer styles (inline in modal view).
		// (CSS is included directly in debug-tracer-modal.php)

		// Enqueue admin scripts.
		wp_enqueue_script(
			'toptour-ref-admin',
			TOPTOUR_REF_PLUGIN_URL . 'admin/assets/admin.js',
			array( 'jquery' ),
			TOPTOUR_REF_VERSION,
			true
		);

		// Enqueue tracer script.
		wp_enqueue_script(
			'toptour-ref-tracer',
			TOPTOUR_REF_PLUGIN_URL . 'admin/assets/tracer.js',
			array(),
			TOPTOUR_REF_VERSION,
			true
		);

		// Localize script for future use.
		wp_localize_script(
			'toptour-ref-admin',
			'toptour_ref_data',
			array(
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'toptour_ref_nonce' ),
				'version' => TOPTOUR_REF_VERSION,
				'rest_nonce' => wp_create_nonce( 'wp_rest' ),
			)
		);

		// Localize tracer script
		wp_localize_script(
			'toptour-ref-tracer',
			'toptourTracerNonce',
			wp_create_nonce( 'wp_rest' )
		);
	}

	/**
	 * Render dashboard page.
	 *
	 * @return void
	 */
	public static function render_dashboard_page() {
		// Load dashboard view.
		include TOPTOUR_REF_PLUGIN_DIR . 'admin/views/dashboard.php';
	}

	/**
	 * Render facilities page.
	 *
	 * @return void
	 */
	public static function render_facilities_page() {
		if ( ! Toptour_Ref_Capabilities::user_can_manage_references() ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'toptour-reference-finder' ) );
		}
		include TOPTOUR_REF_PLUGIN_DIR . 'admin/views/facilities.php';
	}

	/**
	 * Render destinations page.
	 *
	 * @return void
	 */
	public static function render_destinations_page() {
		if ( ! Toptour_Ref_Capabilities::user_can_manage_references() ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'toptour-reference-finder' ) );
		}
		include TOPTOUR_REF_PLUGIN_DIR . 'admin/views/destinations.php';
	}

	/**
	 * Render points of interest page.
	 *
	 * @return void
	 */
	public static function render_points_of_interest_page() {
		if ( ! Toptour_Ref_Capabilities::user_can_manage_references() ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'toptour-reference-finder' ) );
		}
		include TOPTOUR_REF_PLUGIN_DIR . 'admin/views/points-of-interest.php';
	}

	/**
	 * Render contacts page.
	 *
	 * @return void
	 */
	public static function render_contacts_page() {
		if ( ! Toptour_Ref_Capabilities::user_can_manage_references() ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'toptour-reference-finder' ) );
		}
		include TOPTOUR_REF_PLUGIN_DIR . 'admin/views/contacts.php';
	}

	/**
	 * Render interests page.
	 *
	 * @return void
	 */
	public static function render_interests_page() {
		if ( ! Toptour_Ref_Capabilities::user_can_manage_references() ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'toptour-reference-finder' ) );
		}
		include TOPTOUR_REF_PLUGIN_DIR . 'admin/views/interests.php';
	}

	/**
	 * Render offers page.
	 *
	 * @return void
	 */
	public static function render_offers_page() {
		if ( ! Toptour_Ref_Capabilities::user_can_manage_references() ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'toptour-reference-finder' ) );
		}
		include TOPTOUR_REF_PLUGIN_DIR . 'admin/views/offers.php';
	}

	/**
	 * Render sources page.
	 *
	 * @return void
	 */
	public static function render_sources_page() {
		if ( ! Toptour_Ref_Capabilities::user_can_manage_references() ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'toptour-reference-finder' ) );
		}
		include TOPTOUR_REF_PLUGIN_DIR . 'admin/views/reference-sources.php';
	}

	/**
	 * Render findings page.
	 *
	 * @return void
	 */
	public static function render_findings_page() {
		include TOPTOUR_REF_PLUGIN_DIR . 'admin/views/findings.php';
	}

	/**
	 * Render photo evidence page.
	 *
	 * @return void
	 */
	public static function render_photo_evidence_page() {
		include TOPTOUR_REF_PLUGIN_DIR . 'admin/views/photo-evidence.php';
	}

	/**
	 * Render collection tasks page.
	 *
	 * @return void
	 */
	public static function render_collection_page() {
		if ( ! Toptour_Ref_Capabilities::user_can_manage_references() ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'toptour-reference-finder' ) );
		}
		include TOPTOUR_REF_PLUGIN_DIR . 'admin/views/collection-tasks.php';
	}

	/**
	 * Render settings page.
	 *
	 * @return void
	 */
	       public static function render_settings_page() {
		       // Only allow for users with manage_toptour_references capability
		       if ( ! Toptour_Ref_Capabilities::user_can_manage_references() ) {
			       wp_die( esc_html__( 'You do not have permission to access this page.', 'toptour-reference-finder' ) );
		       }
		       include TOPTOUR_REF_PLUGIN_DIR . 'admin/views/settings.php';
	       }

	/**
	 * Render a placeholder page for future implementation.
	 *
	 * @param string $title Page title.
	 * @param string $description Page description.
	 *
	 * @return void
	 */
	private static function render_placeholder_page( $title, $description ) {
		?>
		<div class="wrap toptour-ref-placeholder">
			<h1><?php echo esc_html( $title ); ?></h1>
			<p><?php echo esc_html( $description ); ?></p>
			<div class="notice notice-info">
				<p>
					<strong>MVP Status:</strong>
					<?php esc_html_e( 'Zatiaľ bez dátovej logiky. Sekcia sa implementuje v neskorších verziách.', 'toptour-reference-finder' ); ?>
				</p>
			</div>
		</div>
		<?php
	}
}
