<?php
/**
 * TOPTOUR Reference Finder Settings View
 *
 * Settings and diagnostics page.
 *
 * @package Toptour_Ref
 * @version 0.2.14
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
$ai_notice = '';
$ai_report_notice = '';
$ai_files_notice = '';
$ai_files_preview_notice = '';

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

if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['toptour_ref_ai_bridge_submit'] ) ) {
	check_admin_referer( 'toptour_ref_save_ai_bridge' );
	Toptour_Ref_AI_Bridge::save_settings(
		[
			'ai_bridge_enabled' => absint( $_POST['ai_bridge_enabled'] ?? 0 ),
			'ai_model' => sanitize_text_field( wp_unslash( $_POST['ai_model'] ?? 'gpt-4o-mini' ) ),
			'ai_api_key' => sanitize_text_field( wp_unslash( $_POST['ai_api_key'] ?? '' ) ),
			'ai_max_tokens' => absint( $_POST['ai_max_tokens'] ?? 1800 ),
			'ai_temperature' => floatval( $_POST['ai_temperature'] ?? 0.2 ),
			'ai_batch_limit' => absint( $_POST['ai_batch_limit'] ?? 5 ),
		]
	);
	$ai_notice = __( 'AI bridge nastavenia boli uložené.', 'toptour-reference-finder' );
}

if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['toptour_ref_ai_process_submit'] ) ) {
	check_admin_referer( 'toptour_ref_process_ai_bridge' );
	$process_result = Toptour_Ref_AI_Bridge::process_pending_batches();
	$import_result = Toptour_Ref_AI_Outbox_Importer::process_pending_outbox();
	$ai_notice = sanitize_text_field(
		trim(
			(string) ( $process_result['message'] ?? __( 'AI batch spracovanie ukoncené.', 'toptour-reference-finder' ) ) . ' ' .
			(string) ( $import_result['message'] ?? __( 'AI outbox import ukonceny.', 'toptour-reference-finder' ) )
		)
	);
}

if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['toptour_ref_ai_clear_reports_submit'] ) ) {
	check_admin_referer( 'toptour_ref_clear_ai_reports' );
	$clear_result = Toptour_Ref_AI_Outbox_Importer::clear_import_reports( 0 );
	$ai_report_notice = sprintf(
		/* translators: 1: removed report count */
		__( 'AI reporty boli vymazane. Odstranene zaznamy: %d', 'toptour-reference-finder' ),
		absint( $clear_result['removed'] ?? 0 )
	);
}

if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['toptour_ref_ai_clear_old_reports_submit'] ) ) {
	check_admin_referer( 'toptour_ref_clear_ai_reports' );
	$days = max( 1, absint( $_POST['ai_report_clear_days'] ?? 30 ) );
	$clear_result = Toptour_Ref_AI_Outbox_Importer::clear_import_reports( $days );
	$ai_report_notice = sprintf(
		/* translators: 1: removed report count, 2: day count */
		__( 'Vymazane stare AI reporty: %1$d (starsie ako %2$d dni).', 'toptour-reference-finder' ),
		absint( $clear_result['removed'] ?? 0 ),
		$days
	);
}

if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['toptour_ref_ai_cleanup_files_submit'] ) ) {
	check_admin_referer( 'toptour_ref_cleanup_ai_files' );
	$scope = sanitize_key( (string) ( $_POST['ai_cleanup_scope'] ?? 'archive' ) );
	$days = max( 1, absint( $_POST['ai_cleanup_days'] ?? 30 ) );
	$cleanup_result = Toptour_Ref_AI_Bridge::cleanup_files( $scope, $days, 0 );
	$ai_files_notice = sprintf(
		/* translators: 1: scope, 2: removed, 3: failed */
		__( 'AI subory vycistene (scope: %1$s). Odstranene: %2$d, chyby: %3$d.', 'toptour-reference-finder' ),
		sanitize_text_field( $scope ),
		absint( $cleanup_result['removed'] ?? 0 ),
		absint( $cleanup_result['failed'] ?? 0 )
	);
}

if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['toptour_ref_ai_cleanup_files_all_submit'] ) ) {
	check_admin_referer( 'toptour_ref_cleanup_ai_files' );
	$scope = sanitize_key( (string) ( $_POST['ai_cleanup_scope_all'] ?? 'all' ) );
	$cleanup_result = Toptour_Ref_AI_Bridge::cleanup_files( $scope, 0, 0 );
	$ai_files_notice = sprintf(
		/* translators: 1: scope, 2: removed, 3: failed */
		__( 'AI subory boli hromadne vymazane (scope: %1$s). Odstranene: %2$d, chyby: %3$d.', 'toptour-reference-finder' ),
		sanitize_text_field( $scope ),
		absint( $cleanup_result['removed'] ?? 0 ),
		absint( $cleanup_result['failed'] ?? 0 )
	);
}

if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['toptour_ref_ai_cleanup_files_preview_submit'] ) ) {
	check_admin_referer( 'toptour_ref_cleanup_ai_files' );
	$scope = sanitize_key( (string) ( $_POST['ai_cleanup_scope_preview'] ?? 'archive' ) );
	$days = max( 1, absint( $_POST['ai_cleanup_days_preview'] ?? 30 ) );
	$cleanup_result = Toptour_Ref_AI_Bridge::cleanup_files( $scope, $days, 0, true );

	$details = is_array( $cleanup_result['details'] ?? null ) ? $cleanup_result['details'] : [];
	$parts = [];
	foreach ( $details as $detail_scope => $detail_row ) {
		$parts[] = sanitize_text_field( (string) $detail_scope ) . ':' . absint( $detail_row['would_remove'] ?? 0 );
	}

	$ai_files_preview_notice = sprintf(
		/* translators: 1: scope, 2: day filter, 3: would-remove count, 4: detail list */
		__( 'Nahlad mazania (scope: %1$s, starsie ako %2$d dni): spolu by sa odstranilo %3$d suborov. %4$s', 'toptour-reference-finder' ),
		sanitize_text_field( $scope ),
		$days,
		absint( $cleanup_result['removed'] ?? 0 ) + absint( array_sum( wp_list_pluck( $details, 'would_remove' ) ) ),
		empty( $parts ) ? '' : implode( ' | ', $parts )
	);
}

$finder_mode = Toptour_Ref_Task_Processor::get_mode();
$search_settings = Toptour_Ref_Search_Provider::get_settings();
$ai_settings = Toptour_Ref_AI_Bridge::get_settings();
$ai_paths = Toptour_Ref_AI_Bridge::get_paths();
$ai_dir_stats = Toptour_Ref_AI_Bridge::get_directory_file_stats();
$ai_import_reports = Toptour_Ref_AI_Outbox_Importer::get_import_reports( 100 );
$ai_import_reports_total = Toptour_Ref_AI_Outbox_Importer::get_report_total_count();

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
	<?php if ( $ai_notice ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $ai_notice ); ?></p></div>
	<?php endif; ?>
	<?php if ( $ai_report_notice ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $ai_report_notice ); ?></p></div>
	<?php endif; ?>
	<?php if ( $ai_files_notice ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $ai_files_notice ); ?></p></div>
	<?php endif; ?>
	<?php if ( $ai_files_preview_notice ) : ?>
		<div class="notice notice-info is-dismissible"><p><?php echo esc_html( $ai_files_preview_notice ); ?></p></div>
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

		<h2><?php esc_html_e( 'AI JSON bridge (OpenAI)', 'toptour-reference-finder' ); ?></h2>
		<form method="post" action="">
			<?php wp_nonce_field( 'toptour_ref_save_ai_bridge' ); ?>
			<input type="hidden" name="toptour_ref_ai_bridge_submit" value="1">
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'ai_bridge_enabled', 'toptour-reference-finder' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="ai_bridge_enabled" value="1" <?php checked( ! empty( $ai_settings['ai_bridge_enabled'] ) ); ?>>
							<?php esc_html_e( 'Povoliť AI bridge inbox/outbox spracovanie', 'toptour-reference-finder' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="ai_model"><?php esc_html_e( 'ai_model', 'toptour-reference-finder' ); ?></label></th>
					<td><input type="text" id="ai_model" name="ai_model" class="regular-text" value="<?php echo esc_attr( $ai_settings['ai_model'] ); ?>" placeholder="gpt-4o-mini"></td>
				</tr>
				<tr>
					<th scope="row"><label for="ai_api_key"><?php esc_html_e( 'ai_api_key', 'toptour-reference-finder' ); ?></label></th>
					<td><input type="password" id="ai_api_key" name="ai_api_key" class="regular-text" value="<?php echo esc_attr( $ai_settings['ai_api_key'] ); ?>" autocomplete="new-password"></td>
				</tr>
				<tr>
					<th scope="row"><label for="ai_max_tokens"><?php esc_html_e( 'ai_max_tokens', 'toptour-reference-finder' ); ?></label></th>
					<td><input type="number" min="300" max="8000" id="ai_max_tokens" name="ai_max_tokens" value="<?php echo esc_attr( (int) $ai_settings['ai_max_tokens'] ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="ai_temperature"><?php esc_html_e( 'ai_temperature', 'toptour-reference-finder' ); ?></label></th>
					<td><input type="number" min="0" max="1" step="0.1" id="ai_temperature" name="ai_temperature" value="<?php echo esc_attr( (float) $ai_settings['ai_temperature'] ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="ai_batch_limit"><?php esc_html_e( 'ai_batch_limit', 'toptour-reference-finder' ); ?></label></th>
					<td><input type="number" min="1" max="50" id="ai_batch_limit" name="ai_batch_limit" value="<?php echo esc_attr( (int) $ai_settings['ai_batch_limit'] ); ?>"></td>
				</tr>
			</table>
			<?php submit_button( __( 'Uložiť AI bridge', 'toptour-reference-finder' ), 'secondary', '', false ); ?>
		</form>

		<form method="post" action="" style="margin-top:10px;">
			<?php wp_nonce_field( 'toptour_ref_process_ai_bridge' ); ?>
			<input type="hidden" name="toptour_ref_ai_process_submit" value="1">
			<?php submit_button( __( 'Spracovať AI inbox teraz', 'toptour-reference-finder' ), 'primary', '', false ); ?>
		</form>

		<p class="description">
			<?php esc_html_e( 'Vstupné JSON súbory ukladaj do inbox, výstupné AI JSON budú v outbox. AI nemá priamy prístup k databáze pluginu.', 'toptour-reference-finder' ); ?>
		</p>
		<ul>
			<li><?php esc_html_e( 'Base dir:', 'toptour-reference-finder' ); ?> <strong><?php echo esc_html( $ai_paths['base_dir'] ); ?></strong></li>
			<li><?php esc_html_e( 'Inbox:', 'toptour-reference-finder' ); ?> <strong><?php echo esc_html( $ai_paths['inbox_dir'] ); ?></strong> (<?php echo esc_html( absint( $ai_dir_stats['inbox'] ?? 0 ) ); ?>)</li>
			<li><?php esc_html_e( 'Outbox:', 'toptour-reference-finder' ); ?> <strong><?php echo esc_html( $ai_paths['outbox_dir'] ); ?></strong> (<?php echo esc_html( absint( $ai_dir_stats['outbox'] ?? 0 ) ); ?>)</li>
			<li><?php esc_html_e( 'Archive:', 'toptour-reference-finder' ); ?> <strong><?php echo esc_html( $ai_paths['archive_dir'] ); ?></strong> (<?php echo esc_html( absint( $ai_dir_stats['archive'] ?? 0 ) ); ?>)</li>
			<li><?php esc_html_e( 'Error:', 'toptour-reference-finder' ); ?> <strong><?php echo esc_html( $ai_paths['error_dir'] ); ?></strong> (<?php echo esc_html( absint( $ai_dir_stats['error'] ?? 0 ) ); ?>)</li>
		</ul>

		<h3><?php esc_html_e( 'AI subory - hromadne cistenie', 'toptour-reference-finder' ); ?></h3>
		<p class="description">
			<?php esc_html_e( 'Pri vacsom objeme mozete vycistit subory podla veku alebo hromadne vymazat cely scope.', 'toptour-reference-finder' ); ?>
		</p>

		<form method="post" action="" style="margin:10px 0; display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
			<?php wp_nonce_field( 'toptour_ref_cleanup_ai_files' ); ?>
			<select name="ai_cleanup_scope">
				<option value="archive"><?php esc_html_e( 'archive', 'toptour-reference-finder' ); ?></option>
				<option value="error"><?php esc_html_e( 'error', 'toptour-reference-finder' ); ?></option>
				<option value="outbox"><?php esc_html_e( 'outbox', 'toptour-reference-finder' ); ?></option>
				<option value="inbox"><?php esc_html_e( 'inbox', 'toptour-reference-finder' ); ?></option>
				<option value="all"><?php esc_html_e( 'all', 'toptour-reference-finder' ); ?></option>
			</select>
			<input type="number" min="1" max="3650" name="ai_cleanup_days" value="30" style="width:100px;">
			<input type="hidden" name="toptour_ref_ai_cleanup_files_submit" value="1">
			<?php submit_button( __( 'Vymazat stare subory', 'toptour-reference-finder' ), 'secondary', '', false ); ?>
		</form>

		<form method="post" action="" style="margin:10px 0; display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
			<?php wp_nonce_field( 'toptour_ref_cleanup_ai_files' ); ?>
			<select name="ai_cleanup_scope_preview">
				<option value="archive"><?php esc_html_e( 'archive', 'toptour-reference-finder' ); ?></option>
				<option value="error"><?php esc_html_e( 'error', 'toptour-reference-finder' ); ?></option>
				<option value="outbox"><?php esc_html_e( 'outbox', 'toptour-reference-finder' ); ?></option>
				<option value="inbox"><?php esc_html_e( 'inbox', 'toptour-reference-finder' ); ?></option>
				<option value="all" selected><?php esc_html_e( 'all', 'toptour-reference-finder' ); ?></option>
			</select>
			<input type="number" min="1" max="3650" name="ai_cleanup_days_preview" value="30" style="width:100px;">
			<input type="hidden" name="toptour_ref_ai_cleanup_files_preview_submit" value="1">
			<?php submit_button( __( 'Nahlad mazania (dry-run)', 'toptour-reference-finder' ), 'secondary', '', false ); ?>
		</form>

		<form method="post" action="" style="margin:10px 0; display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
			<?php wp_nonce_field( 'toptour_ref_cleanup_ai_files' ); ?>
			<select name="ai_cleanup_scope_all">
				<option value="archive"><?php esc_html_e( 'archive', 'toptour-reference-finder' ); ?></option>
				<option value="error"><?php esc_html_e( 'error', 'toptour-reference-finder' ); ?></option>
				<option value="outbox"><?php esc_html_e( 'outbox', 'toptour-reference-finder' ); ?></option>
				<option value="inbox"><?php esc_html_e( 'inbox', 'toptour-reference-finder' ); ?></option>
				<option value="all" selected><?php esc_html_e( 'all', 'toptour-reference-finder' ); ?></option>
			</select>
			<input type="hidden" name="toptour_ref_ai_cleanup_files_all_submit" value="1">
			<?php submit_button( __( 'Vymazat vsetky subory v scope', 'toptour-reference-finder' ), 'delete', '', false ); ?>
		</form>

		<h3><?php esc_html_e( 'AI import reporty', 'toptour-reference-finder' ); ?></h3>
		<p class="description">
			<?php esc_html_e( 'Import reporty sa ukladaju do limitovanej historie. Pri velkom objeme je mozne reporty hromadne mazat.', 'toptour-reference-finder' ); ?>
		</p>
		<p>
			<?php
			echo esc_html(
				sprintf(
					/* translators: 1: total report count */
					__( 'Celkovo ulozenych reportov: %d', 'toptour-reference-finder' ),
					absint( $ai_import_reports_total )
				)
			);
			?>
		</p>

		<form method="post" action="" style="margin:10px 0; display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
			<?php wp_nonce_field( 'toptour_ref_clear_ai_reports' ); ?>
			<input type="number" min="1" max="3650" name="ai_report_clear_days" value="30" style="width:100px;">
			<input type="hidden" name="toptour_ref_ai_clear_old_reports_submit" value="1">
			<?php submit_button( __( 'Vymazat stare reporty', 'toptour-reference-finder' ), 'secondary', '', false ); ?>
		</form>

		<form method="post" action="" style="margin:10px 0;">
			<?php wp_nonce_field( 'toptour_ref_clear_ai_reports' ); ?>
			<input type="hidden" name="toptour_ref_ai_clear_reports_submit" value="1">
			<?php submit_button( __( 'Vymazat vsetky AI reporty', 'toptour-reference-finder' ), 'delete', '', false ); ?>
		</form>

		<?php if ( ! empty( $ai_import_reports ) ) : ?>
			<table class="widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Cas', 'toptour-reference-finder' ); ?></th>
						<th><?php esc_html_e( 'Stav', 'toptour-reference-finder' ); ?></th>
						<th><?php esc_html_e( 'Task / Run', 'toptour-reference-finder' ); ?></th>
						<th><?php esc_html_e( 'Outbox', 'toptour-reference-finder' ); ?></th>
						<th><?php esc_html_e( 'Suhrn', 'toptour-reference-finder' ); ?></th>
						<th><?php esc_html_e( 'Moduly', 'toptour-reference-finder' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $ai_import_reports as $report_row ) : ?>
					<tr>
						<td><?php echo esc_html( $report_row['created_at'] ?? '' ); ?></td>
						<td><?php echo ! empty( $report_row['success'] ) ? esc_html__( 'OK', 'toptour-reference-finder' ) : esc_html__( 'CHYBA', 'toptour-reference-finder' ); ?></td>
						<td><?php echo esc_html( 'T#' . absint( $report_row['task_id'] ?? 0 ) . ' / R#' . absint( $report_row['run_id'] ?? 0 ) ); ?></td>
						<td><?php echo esc_html( (string) ( $report_row['outbox_file'] ?? '' ) ); ?></td>
						<td>
							<?php
							echo esc_html( (string) ( $report_row['message'] ?? '' ) );
							echo '<br>';
							echo esc_html(
								sprintf(
									'f=%d n=%d d=%d e=%d',
									absint( $report_row['metrics']['found_count'] ?? 0 ),
									absint( $report_row['metrics']['new_count'] ?? 0 ),
									absint( $report_row['metrics']['duplicate_count'] ?? 0 ),
									absint( $report_row['metrics']['error_count'] ?? 0 )
								)
							);
							?>
						</td>
						<td>
							<?php
							$mm = is_array( $report_row['module_metrics'] ?? null ) ? $report_row['module_metrics'] : [];
							foreach ( [ 'sources', 'facilities', 'destinations', 'points_of_interest', 'contacts', 'interests', 'findings', 'photo_evidence' ] as $mm_key ) {
								$mr = is_array( $mm[ $mm_key ] ?? null ) ? $mm[ $mm_key ] : [];
								echo esc_html( $mm_key . ': c=' . absint( $mr['created'] ?? 0 ) . ' u=' . absint( $mr['updated'] ?? 0 ) . ' e=' . absint( $mr['errors'] ?? 0 ) );
								echo '<br>';
							}
							?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php else : ?>
			<p><?php esc_html_e( 'Zatial nie su dostupne AI import reporty.', 'toptour-reference-finder' ); ?></p>
		<?php endif; ?>

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
