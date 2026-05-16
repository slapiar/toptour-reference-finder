<?php
/**
 * TOPTOUR Reference Finder Settings View
 *
 * Settings and diagnostics page.
 *
 * @package Toptour_Ref
 * @version 0.1.2
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;


$tables = [
	'toptour_ref_facilities',
	'toptour_ref_destinations',
	'toptour_ref_signal_patterns',
	'toptour_ref_collection_tasks',
	'toptour_ref_facility_destination',
	'toptour_ref_contacts',
	'toptour_ref_resident_profiles',
	'toptour_ref_interests',
	'toptour_ref_contact_interests',
	'toptour_ref_contact_relationships',
	'toptour_ref_contact_influence',
	'toptour_ref_points_of_interest',
	'toptour_ref_offers',
	'toptour_ref_sources',
	'toptour_ref_mail_templates',
	'toptour_ref_mail_queue',
	'toptour_ref_findings',
	'toptour_ref_photo_evidence',
	'toptour_ref_discovery_runs',
	'toptour_ref_discovery_candidates',
	'toptour_ref_discovery_missing_fields',
	'toptour_ref_offer_snapshots',
];

$prefix = $wpdb->prefix;
$now = current_time( 'mysql' );
$mode_notice = '';
$search_notice = '';

if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['toptour_ref_mode_submit'] ) ) {
	check_admin_referer( 'toptour_ref_save_mode' );
	$requested_mode = sanitize_text_field( wp_unslash( $_POST['toptour_ref_finder_mode'] ?? 'manual' ) );
	$saved_mode = Toptour_Ref_Task_Processor::set_mode( $requested_mode );
	$mode_notice = 'automatic' === $saved_mode
		? __( 'Automatický režim bol zapnutý.', 'toptour-reference-finder' )
		: __( 'Manuálny režim bol zapnutý.', 'toptour-reference-finder' );
}

if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['toptour_ref_search_provider_submit'] ) ) {
	check_admin_referer( 'toptour_ref_save_search_provider' );
	Toptour_Ref_Search_Provider::save_settings(
		[
			'search_provider_enabled' => absint( $_POST['search_provider_enabled'] ?? 0 ),
			'search_provider_type' => sanitize_text_field( wp_unslash( $_POST['search_provider_type'] ?? 'existing_candidates_only' ) ),
			'search_provider_endpoint' => esc_url_raw( wp_unslash( $_POST['search_provider_endpoint'] ?? '' ) ),
			'search_provider_api_key' => sanitize_text_field( wp_unslash( $_POST['search_provider_api_key'] ?? '' ) ),
			'max_search_results_per_task' => absint( $_POST['max_search_results_per_task'] ?? 15 ),
		]
	);
	$search_notice = __( 'Nastavenia vyhľadávacieho zdroja boli uložené.', 'toptour-reference-finder' );
}

$finder_mode = Toptour_Ref_Task_Processor::get_mode();
$search_settings = Toptour_Ref_Search_Provider::get_settings();

// Prepare diagnostics data
$diagnostics = [];
foreach ( $tables as $table ) {
	$full_table = $prefix . $table;
	$exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $full_table ) );
	$count = null;
	if ( $exists ) {
		$count = $wpdb->get_var( "SELECT COUNT(*) FROM $full_table" );
	}
	$diagnostics[] = [
		'name'   => $full_table,
		'exists' => (bool) $exists,
		'count'  => $count,
	];
}

// Check signal pattern seeds
$signal_seeds_status = [];
$signal_table = $prefix . 'toptour_ref_signal_patterns';
$seed_keys = [
	'cleanliness_positive',
	'cleanliness_risk',
	'official_vs_guest_photo_contradiction',
	'staff_positive',
	'noise_risk',
	'location_accessibility_uncertainty',
	'suspicious_review_similarity',
	'guest_photo_positive_surprise',
];
if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $signal_table ) ) ) {
	foreach ( $seed_keys as $key ) {
		$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $signal_table WHERE pattern_key = %s", $key ) );
		$signal_seeds_status[] = [
			'key' => $key,
			'exists' => (bool) $exists,
		];
	}
}
?>
<div class="wrap toptour-ref-settings">
	<h1><?php esc_html_e( 'TOPTOUR Reference Finder – Settings & Diagnostics', 'toptour-reference-finder' ); ?></h1>

	<?php if ( $mode_notice ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $mode_notice ); ?></p></div>
	<?php endif; ?>
	<?php if ( $search_notice ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $search_notice ); ?></p></div>
	<?php endif; ?>

	<h2><?php esc_html_e( 'Finder mode', 'toptour-reference-finder' ); ?></h2>
	<form method="post" action="">
		<?php wp_nonce_field( 'toptour_ref_save_mode' ); ?>
		<input type="hidden" name="toptour_ref_mode_submit" value="1">
		<select name="toptour_ref_finder_mode">
			<option value="manual" <?php selected( $finder_mode, 'manual' ); ?>><?php esc_html_e( 'Manual', 'toptour-reference-finder' ); ?></option>
			<option value="automatic" <?php selected( $finder_mode, 'automatic' ); ?>><?php esc_html_e( 'Automatic', 'toptour-reference-finder' ); ?></option>
		</select>
		<?php submit_button( __( 'Uložiť režim', 'toptour-reference-finder' ), 'secondary', '', false ); ?>
	</form>
	<p>
		<?php if ( 'manual' === $finder_mode ) : ?>
			<?php esc_html_e( 'Automatické spúšťanie je vypnuté. Úlohy sa spúšťajú iba ručne.', 'toptour-reference-finder' ); ?>
		<?php else : ?>
			<?php esc_html_e( 'Automatické spúšťanie je zapnuté. Aktívne úlohy sa spracujú podľa frequency a next_run_at.', 'toptour-reference-finder' ); ?>
		<?php endif; ?>
	</p>

	<h2><?php esc_html_e( 'Vyhľadávací zdroj', 'toptour-reference-finder' ); ?></h2>
	<form method="post" action="">
		<?php wp_nonce_field( 'toptour_ref_save_search_provider' ); ?>
		<input type="hidden" name="toptour_ref_search_provider_submit" value="1">
		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'search_provider_enabled', 'toptour-reference-finder' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="search_provider_enabled" value="1" <?php checked( ! empty( $search_settings['search_provider_enabled'] ) ); ?>>
						<?php esc_html_e( 'Povoliť vyhľadávací provider', 'toptour-reference-finder' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="search_provider_type"><?php esc_html_e( 'search_provider_type', 'toptour-reference-finder' ); ?></label></th>
				<td>
					<select id="search_provider_type" name="search_provider_type">
						<option value="existing_candidates_only" <?php selected( $search_settings['search_provider_type'], 'existing_candidates_only' ); ?>><?php esc_html_e( 'existing_candidates_only', 'toptour-reference-finder' ); ?></option>
						<option value="configured_api" <?php selected( $search_settings['search_provider_type'], 'configured_api' ); ?>><?php esc_html_e( 'configured_api', 'toptour-reference-finder' ); ?></option>
						<option value="disabled" <?php selected( $search_settings['search_provider_type'], 'disabled' ); ?>><?php esc_html_e( 'disabled', 'toptour-reference-finder' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="search_provider_endpoint"><?php esc_html_e( 'search_provider_endpoint', 'toptour-reference-finder' ); ?></label></th>
				<td><input type="url" id="search_provider_endpoint" name="search_provider_endpoint" class="regular-text" value="<?php echo esc_attr( $search_settings['search_provider_endpoint'] ); ?>" placeholder="https://api.example.com/search"></td>
			</tr>
			<tr>
				<th scope="row"><label for="search_provider_api_key"><?php esc_html_e( 'search_provider_api_key', 'toptour-reference-finder' ); ?></label></th>
				<td><input type="password" id="search_provider_api_key" name="search_provider_api_key" class="regular-text" value="<?php echo esc_attr( $search_settings['search_provider_api_key'] ); ?>" autocomplete="new-password"></td>
			</tr>
			<tr>
				<th scope="row"><label for="max_search_results_per_task"><?php esc_html_e( 'max_search_results_per_task', 'toptour-reference-finder' ); ?></label></th>
				<td><input type="number" min="1" max="100" id="max_search_results_per_task" name="max_search_results_per_task" value="<?php echo esc_attr( (int) $search_settings['max_search_results_per_task'] ); ?>"></td>
			</tr>
		</table>
		<?php submit_button( __( 'Uložiť vyhľadávací zdroj', 'toptour-reference-finder' ), 'secondary', '', false ); ?>
	</form>

	<h2><?php esc_html_e( 'Database status', 'toptour-reference-finder' ); ?></h2>
	<table class="widefat fixed striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Table', 'toptour-reference-finder' ); ?></th>
				<th><?php esc_html_e( 'Exists', 'toptour-reference-finder' ); ?></th>
				<th><?php esc_html_e( 'Row count', 'toptour-reference-finder' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php foreach ( $diagnostics as $row ) : ?>
			<tr>
				<td><?php echo esc_html( $row['name'] ); ?></td>
				<td><?php echo esc_html( $row['exists'] ? 'exists' : 'missing' ); ?></td>
				<td><?php echo $row['exists'] ? esc_html( $row['count'] ) : '-'; ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>

	<h3><?php esc_html_e( 'Signal pattern seed status', 'toptour-reference-finder' ); ?></h3>
	<?php if ( $signal_seeds_status ) : ?>
		<table class="widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Pattern key', 'toptour-reference-finder' ); ?></th>
					<th><?php esc_html_e( 'Exists', 'toptour-reference-finder' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $signal_seeds_status as $seed ) : ?>
				<tr>
					<td><?php echo esc_html( $seed['key'] ); ?></td>
					<td><?php echo esc_html( $seed['exists'] ? 'yes' : 'no' ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	<?php else : ?>
		<p><?php esc_html_e( 'Signal pattern table does not exist.', 'toptour-reference-finder' ); ?></p>
	<?php endif; ?>

	<h3><?php esc_html_e( 'Plugin & DB version info', 'toptour-reference-finder' ); ?></h3>
	<ul>
		<li><?php esc_html_e( 'Plugin version:', 'toptour-reference-finder' ); ?> <strong><?php echo esc_html( defined( 'TOPTOUR_REF_VERSION' ) ? TOPTOUR_REF_VERSION : 'n/a' ); ?></strong></li>
		<li><?php esc_html_e( 'DB version constant:', 'toptour-reference-finder' ); ?> <strong><?php echo esc_html( defined( 'TOPTOUR_REF_DB_VERSION' ) ? TOPTOUR_REF_DB_VERSION : 'n/a' ); ?></strong></li>
		<li><?php esc_html_e( 'Stored DB version:', 'toptour-reference-finder' ); ?> <strong><?php echo esc_html( get_option( 'toptour_ref_db_version', 'n/a' ) ); ?></strong></li>
		<li><?php esc_html_e( 'Check time:', 'toptour-reference-finder' ); ?> <strong><?php echo esc_html( $now ); ?></strong></li>
	</ul>
</div>
