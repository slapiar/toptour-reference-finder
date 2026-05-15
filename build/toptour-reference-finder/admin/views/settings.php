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
	'toptour_ref_sources',
	'toptour_ref_mail_templates',
	'toptour_ref_mail_queue',
	'toptour_ref_findings',
	'toptour_ref_photo_evidence',
	'toptour_ref_discovery_runs',
	'toptour_ref_discovery_candidates',
	'toptour_ref_discovery_missing_fields',
];

$prefix = $wpdb->prefix;
$now = current_time( 'mysql' );

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
