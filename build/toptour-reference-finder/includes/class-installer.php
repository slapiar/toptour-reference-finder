<?php
/**
 * Plugin installer class.
 *
 * Handles plugin activation, deactivation and database setup.
 *
 * @package Toptour_Ref
 * @version 0.1.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Installer class for handling plugin activation and setup.
 */
class Toptour_Ref_Installer {
	/**
	 * Plugin activation hook callback.
	 *
	 * @return void
	 */
	public static function activate() {
		// Store plugin version.
		update_option( 'toptour_ref_version', TOPTOUR_REF_VERSION );

		// Create database tables and seed defaults.
		self::create_tables();
		self::seed_signal_patterns();
		self::seed_interests();
		self::seed_mail_templates();

		// Store database schema version.
		update_option( 'toptour_ref_db_version', TOPTOUR_REF_DB_VERSION );

		// Set plugin activated flag.
		update_option( 'toptour_ref_activated', true );

		// Flush rewrite rules for future REST API routes.
		flush_rewrite_rules();
	}

	/**
	 * Maybe upgrade database schema if needed.
	 *
	 * @return void
	 */
	public static function maybe_upgrade() {
		$stored_version = self::get_schema_version();
		if ( version_compare( $stored_version, TOPTOUR_REF_DB_VERSION, '<' ) ) {
			self::create_tables();
			self::seed_signal_patterns();
			self::seed_interests();
			self::seed_mail_templates();
			self::update_schema_version();
		}
	}

	/**
	 * Create plugin database tables using dbDelta().
	 *
	 * @return void
	 */
	public static function create_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset_collate = $wpdb->get_charset_collate();

		$tables = [];

		$tables[] = "CREATE TABLE {$wpdb->prefix}toptour_ref_facilities (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		name varchar(255) NOT NULL,
		slug varchar(200) NOT NULL,
		facility_type varchar(80) DEFAULT '',
		country varchar(100) DEFAULT '',
		region varchar(150) DEFAULT '',
		city varchar(150) DEFAULT '',
		address text NULL,
		website_url text NULL,
		official_source_url text NULL,
		status varchar(50) DEFAULT 'draft',
		notes longtext NULL,
		created_at datetime NOT NULL,
		updated_at datetime NOT NULL,
		PRIMARY KEY  (id),
		KEY slug (slug),
		KEY facility_type (facility_type),
		KEY country (country),
		KEY region (region),
		KEY status (status)
	) {$charset_collate};";

		$tables[] = "CREATE TABLE {$wpdb->prefix}toptour_ref_destinations (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		name varchar(255) NOT NULL,
		slug varchar(200) NOT NULL,
		country varchar(100) DEFAULT '',
		region varchar(150) DEFAULT '',
		destination_type varchar(80) DEFAULT '',
		seasonality varchar(150) DEFAULT '',
		description longtext NULL,
		notes longtext NULL,
		status varchar(50) DEFAULT 'draft',
		created_at datetime NOT NULL,
		updated_at datetime NOT NULL,
		PRIMARY KEY  (id),
		KEY slug (slug),
		KEY country (country),
		KEY region (region),
		KEY destination_type (destination_type),
		KEY status (status)
	) {$charset_collate};";

		$tables[] = "CREATE TABLE {$wpdb->prefix}toptour_ref_signal_patterns (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		pattern_key varchar(120) NOT NULL,
		name varchar(255) NOT NULL,
		description longtext NULL,
		pattern_type varchar(80) DEFAULT '',
		source_type varchar(80) DEFAULT '',
		evidence_method varchar(80) DEFAULT '',
		default_weight_hint varchar(50) DEFAULT '',
		is_active tinyint(1) DEFAULT 1,
		created_at datetime NOT NULL,
		updated_at datetime NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY pattern_key (pattern_key),
		KEY pattern_type (pattern_type),
		KEY source_type (source_type),
		KEY evidence_method (evidence_method),
		KEY is_active (is_active)
	) {$charset_collate};";

		$tables[] = "CREATE TABLE {$wpdb->prefix}toptour_ref_collection_tasks (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		task_title varchar(255) NOT NULL,
		target_type varchar(80) DEFAULT '',
		target_id bigint(20) unsigned DEFAULT 0,
		query_text longtext NULL,
		source_hint text NULL,
		expected_source_type varchar(80) DEFAULT '',
		task_status varchar(50) DEFAULT 'pending',
		priority varchar(50) DEFAULT 'normal',
		assigned_to bigint(20) unsigned DEFAULT 0,
		attempts int(10) unsigned DEFAULT 0,
		last_run_at datetime NULL,
		completed_at datetime NULL,
		notes longtext NULL,
		created_at datetime NOT NULL,
		updated_at datetime NOT NULL,
		PRIMARY KEY  (id),
		KEY target_type (target_type),
		KEY target_id (target_id),
		KEY task_status (task_status),
		KEY priority (priority),
		KEY assigned_to (assigned_to),
		KEY last_run_at (last_run_at)
	) {$charset_collate};";

		$tables[] = "CREATE TABLE {$wpdb->prefix}toptour_ref_discovery_runs (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		collection_task_id bigint(20) unsigned NOT NULL,
		run_title varchar(255) DEFAULT '',
		input_summary longtext NULL,
		resolved_target_type varchar(80) DEFAULT 'general',
		resolved_target_id bigint(20) unsigned DEFAULT 0,
		resolved_target_label varchar(255) DEFAULT '',
		detected_destination varchar(255) DEFAULT '',
		detected_facility varchar(255) DEFAULT '',
		detected_interests longtext NULL,
		detected_finding_areas longtext NULL,
		missing_fields longtext NULL,
		search_queries longtext NULL,
		discovery_provider varchar(80) DEFAULT 'manual',
		run_status varchar(50) DEFAULT 'draft',
		run_notes longtext NULL,
		created_at datetime NOT NULL,
		updated_at datetime NOT NULL,
		completed_at datetime NULL,
		PRIMARY KEY  (id),
		KEY collection_task_id (collection_task_id),
		KEY resolved_target_type (resolved_target_type),
		KEY resolved_target_id (resolved_target_id),
		KEY discovery_provider (discovery_provider),
		KEY run_status (run_status),
		KEY created_at (created_at)
	) {$charset_collate};";

		$tables[] = "CREATE TABLE {$wpdb->prefix}toptour_ref_discovery_candidates (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		discovery_run_id bigint(20) unsigned NOT NULL,
		collection_task_id bigint(20) unsigned DEFAULT 0,
		candidate_title varchar(255) NOT NULL,
		candidate_url text NULL,
		candidate_platform varchar(120) DEFAULT '',
		candidate_source_type varchar(80) DEFAULT 'other',
		candidate_origin varchar(80) DEFAULT 'manual_discovery',
		snippet longtext NULL,
		detected_language varchar(50) DEFAULT '',
		suggested_target_type varchar(80) DEFAULT 'general',
		suggested_target_id bigint(20) unsigned DEFAULT 0,
		suggested_credibility_level varchar(50) DEFAULT 'unknown',
		suggestion_reason longtext NULL,
		search_query varchar(255) DEFAULT '',
		candidate_status varchar(50) DEFAULT 'new',
		accepted_source_id bigint(20) unsigned DEFAULT 0,
		notes longtext NULL,
		created_at datetime NOT NULL,
		updated_at datetime NOT NULL,
		PRIMARY KEY  (id),
		KEY discovery_run_id (discovery_run_id),
		KEY collection_task_id (collection_task_id),
		KEY candidate_source_type (candidate_source_type),
		KEY candidate_origin (candidate_origin),
		KEY suggested_target_type (suggested_target_type),
		KEY suggested_target_id (suggested_target_id),
		KEY suggested_credibility_level (suggested_credibility_level),
		KEY candidate_status (candidate_status),
		KEY accepted_source_id (accepted_source_id)
	) {$charset_collate};";

		$tables[] = "CREATE TABLE {$wpdb->prefix}toptour_ref_discovery_missing_fields (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		discovery_run_id bigint(20) unsigned NOT NULL,
		collection_task_id bigint(20) unsigned DEFAULT 0,
		field_key varchar(120) NOT NULL,
		field_label varchar(255) NOT NULL,
		field_type varchar(80) DEFAULT 'text',
		field_value longtext NULL,
		is_required tinyint(1) DEFAULT 1,
		field_status varchar(50) DEFAULT 'missing',
		help_text longtext NULL,
		created_at datetime NOT NULL,
		updated_at datetime NOT NULL,
		PRIMARY KEY  (id),
		KEY discovery_run_id (discovery_run_id),
		KEY collection_task_id (collection_task_id),
		KEY field_key (field_key),
		KEY field_status (field_status),
		KEY is_required (is_required)
	) {$charset_collate};";

		$tables[] = "CREATE TABLE {$wpdb->prefix}toptour_ref_facility_destination (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		facility_id bigint(20) unsigned NOT NULL,
		destination_id bigint(20) unsigned NOT NULL,
		relation_type varchar(80) DEFAULT 'primary_area',
		is_primary tinyint(1) DEFAULT 0,
		notes longtext NULL,
		created_at datetime NOT NULL,
		updated_at datetime NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY facility_destination (facility_id,destination_id),
		KEY facility_id (facility_id),
		KEY destination_id (destination_id),
		KEY relation_type (relation_type),
		KEY is_primary (is_primary)
	) {$charset_collate};";

		$tables[] = "CREATE TABLE {$wpdb->prefix}toptour_ref_contacts (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		contact_type varchar(80) DEFAULT 'person',
		display_name varchar(255) NOT NULL,
		first_name varchar(150) DEFAULT '',
		last_name varchar(150) DEFAULT '',
		organization_name varchar(255) DEFAULT '',
		email varchar(190) DEFAULT '',
		phone varchar(80) DEFAULT '',
		website_url text NULL,
		country varchar(100) DEFAULT '',
		region varchar(150) DEFAULT '',
		city varchar(150) DEFAULT '',
		address text NULL,
		preferred_language varchar(50) DEFAULT '',
		status varchar(50) DEFAULT 'draft',
		trust_level varchar(50) DEFAULT 'unknown',
		notes longtext NULL,
		created_at datetime NOT NULL,
		updated_at datetime NOT NULL,
		PRIMARY KEY  (id),
		KEY contact_type (contact_type),
		KEY display_name (display_name),
		KEY email (email),
		KEY country (country),
		KEY region (region),
		KEY city (city),
		KEY status (status),
		KEY trust_level (trust_level)
	) {$charset_collate};";

		$tables[] = "CREATE TABLE {$wpdb->prefix}toptour_ref_resident_profiles (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		contact_id bigint(20) unsigned NOT NULL,
		resident_type varchar(80) DEFAULT 'local_helper',
		availability_status varchar(80) DEFAULT 'unknown',
		verification_status varchar(80) DEFAULT 'unverified',
		badge_status varchar(80) DEFAULT 'none',
		qr_code_token varchar(190) DEFAULT '',
		notes longtext NULL,
		created_at datetime NOT NULL,
		updated_at datetime NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY contact_id (contact_id),
		KEY resident_type (resident_type),
		KEY availability_status (availability_status),
		KEY verification_status (verification_status),
		KEY badge_status (badge_status),
		KEY qr_code_token (qr_code_token)
	) {$charset_collate};";

		$tables[] = "CREATE TABLE {$wpdb->prefix}toptour_ref_interests (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		interest_key varchar(120) NOT NULL,
		name varchar(255) NOT NULL,
		description longtext NULL,
		interest_type varchar(80) DEFAULT 'other',
		is_active tinyint(1) DEFAULT 1,
		created_at datetime NOT NULL,
		updated_at datetime NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY interest_key (interest_key),
		KEY interest_type (interest_type),
		KEY is_active (is_active)
	) {$charset_collate};";

		$tables[] = "CREATE TABLE {$wpdb->prefix}toptour_ref_contact_interests (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		contact_id bigint(20) unsigned NOT NULL,
		interest_id bigint(20) unsigned NOT NULL,
		interest_level varchar(80) DEFAULT 'medium',
		relationship_type varchar(80) DEFAULT 'personal_interest',
		notes longtext NULL,
		created_at datetime NOT NULL,
		updated_at datetime NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY contact_interest (contact_id,interest_id,relationship_type),
		KEY contact_id (contact_id),
		KEY interest_id (interest_id),
		KEY interest_level (interest_level),
		KEY relationship_type (relationship_type)
	) {$charset_collate};";

		$tables[] = "CREATE TABLE {$wpdb->prefix}toptour_ref_contact_relationships (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		contact_id bigint(20) unsigned NOT NULL,
		related_contact_id bigint(20) unsigned NOT NULL,
		relationship_type varchar(80) DEFAULT 'knows',
		relationship_strength varchar(80) DEFAULT 'medium',
		mutuality_level varchar(80) DEFAULT 'unknown',
		trust_note text NULL,
		notes longtext NULL,
		created_at datetime NOT NULL,
		updated_at datetime NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY contact_relation (contact_id,related_contact_id,relationship_type),
		KEY contact_id (contact_id),
		KEY related_contact_id (related_contact_id),
		KEY relationship_type (relationship_type),
		KEY relationship_strength (relationship_strength),
		KEY mutuality_level (mutuality_level)
	) {$charset_collate};";

		$tables[] = "CREATE TABLE {$wpdb->prefix}toptour_ref_contact_influence (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		contact_id bigint(20) unsigned NOT NULL,
		target_type varchar(80) DEFAULT 'general',
		target_id bigint(20) unsigned DEFAULT 0,
		point_label varchar(255) DEFAULT '',
		influence_type varchar(80) DEFAULT '',
		influence_level varchar(80) DEFAULT 'unknown',
		usefulness_level varchar(80) DEFAULT 'unknown',
		mutuality_level varchar(80) DEFAULT 'unknown',
		evidence_note text NULL,
		notes longtext NULL,
		created_at datetime NOT NULL,
		updated_at datetime NOT NULL,
		PRIMARY KEY  (id),
		KEY contact_id (contact_id),
		KEY target_type (target_type),
		KEY target_id (target_id),
		KEY point_label (point_label),
		KEY influence_type (influence_type),
		KEY influence_level (influence_level),
		KEY usefulness_level (usefulness_level),
		KEY mutuality_level (mutuality_level)
	) {$charset_collate};";

		$tables[] = "CREATE TABLE {$wpdb->prefix}toptour_ref_points_of_interest (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		destination_id bigint(20) unsigned DEFAULT 0,
		facility_id bigint(20) unsigned DEFAULT 0,
		name varchar(255) NOT NULL,
		slug varchar(200) NOT NULL,
		poi_type varchar(80) DEFAULT 'other',
		country varchar(100) DEFAULT '',
		region varchar(150) DEFAULT '',
		city varchar(150) DEFAULT '',
		address text NULL,
		latitude decimal(10,7) NULL,
		longitude decimal(10,7) NULL,
		description longtext NULL,
		status varchar(50) DEFAULT 'draft',
		notes longtext NULL,
		created_at datetime NOT NULL,
		updated_at datetime NOT NULL,
		PRIMARY KEY  (id),
		KEY destination_id (destination_id),
		KEY facility_id (facility_id),
		KEY slug (slug),
		KEY poi_type (poi_type),
		KEY country (country),
		KEY region (region),
		KEY city (city),
		KEY status (status)
	) {$charset_collate};";

		$tables[] = "CREATE TABLE {$wpdb->prefix}toptour_ref_sources (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		source_title varchar(255) NOT NULL,
		source_url text NULL,
		source_platform varchar(120) DEFAULT '',
		source_type varchar(80) DEFAULT 'review',
		source_origin varchar(80) DEFAULT 'unknown',
		target_type varchar(80) DEFAULT 'general',
		target_id bigint(20) unsigned DEFAULT 0,
		collection_task_id bigint(20) unsigned DEFAULT 0,
		language varchar(50) DEFAULT '',
		captured_at datetime NULL,
		source_date datetime NULL,
		external_rating varchar(80) DEFAULT '',
		external_review_count int(10) unsigned DEFAULT 0,
		credibility_level varchar(50) DEFAULT 'unknown',
		credibility_reason longtext NULL,
		credibility_updated_at datetime NULL,
		verification_method varchar(80) DEFAULT 'manual',
		verification_notes longtext NULL,
		last_verified_at datetime NULL,
		suggested_credibility_level varchar(50) DEFAULT '',
		suggestion_reason longtext NULL,
		suggestion_status varchar(50) DEFAULT 'none',
		suggestion_created_at datetime NULL,
		suggestion_resolved_at datetime NULL,
		suggestion_reviewed_by bigint(20) unsigned DEFAULT 0,
		search_priority varchar(50) DEFAULT 'normal',
		next_action varchar(80) DEFAULT 'review_source',
		validation_status varchar(50) DEFAULT 'new',
		access_status varchar(50) DEFAULT 'unknown',
		notes longtext NULL,
		created_at datetime NOT NULL,
		updated_at datetime NOT NULL,
		PRIMARY KEY  (id),
		KEY source_platform (source_platform),
		KEY source_type (source_type),
		KEY source_origin (source_origin),
		KEY target_type (target_type),
		KEY target_id (target_id),
		KEY collection_task_id (collection_task_id),
		KEY credibility_level (credibility_level),
		KEY credibility_updated_at (credibility_updated_at),
		KEY verification_method (verification_method),
		KEY suggested_credibility_level (suggested_credibility_level),
		KEY suggestion_status (suggestion_status),
		KEY suggestion_created_at (suggestion_created_at),
		KEY suggestion_resolved_at (suggestion_resolved_at),
		KEY suggestion_reviewed_by (suggestion_reviewed_by),
		KEY search_priority (search_priority),
		KEY next_action (next_action),
		KEY validation_status (validation_status),
		KEY access_status (access_status),
		KEY captured_at (captured_at),
		KEY last_verified_at (last_verified_at)
	) {$charset_collate};";

		$tables[] = "CREATE TABLE {$wpdb->prefix}toptour_ref_mail_templates (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		template_key varchar(120) NOT NULL,
		template_name varchar(255) NOT NULL,
		subject varchar(255) NOT NULL,
		body longtext NOT NULL,
		recipient_role varchar(120) DEFAULT 'manager',
		is_active tinyint(1) DEFAULT 1,
		created_at datetime NOT NULL,
		updated_at datetime NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY template_key (template_key),
		KEY recipient_role (recipient_role),
		KEY is_active (is_active)
	) {$charset_collate};";

		$tables[] = "CREATE TABLE {$wpdb->prefix}toptour_ref_mail_queue (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		template_key varchar(120) DEFAULT '',
		related_type varchar(80) DEFAULT '',
		related_id bigint(20) unsigned DEFAULT 0,
		recipient_email varchar(190) DEFAULT '',
		recipient_user_id bigint(20) unsigned DEFAULT 0,
		subject varchar(255) NOT NULL,
		body longtext NOT NULL,
		mail_status varchar(50) DEFAULT 'draft',
		send_attempts int(10) unsigned DEFAULT 0,
		last_error longtext NULL,
		scheduled_at datetime NULL,
		sent_at datetime NULL,
		created_at datetime NOT NULL,
		updated_at datetime NOT NULL,
		PRIMARY KEY  (id),
		KEY template_key (template_key),
		KEY related_type (related_type),
		KEY related_id (related_id),
		KEY recipient_email (recipient_email),
		KEY recipient_user_id (recipient_user_id),
		KEY mail_status (mail_status),
		KEY scheduled_at (scheduled_at),
		KEY sent_at (sent_at)
	) {$charset_collate};";

		$tables[] = "CREATE TABLE {$wpdb->prefix}toptour_ref_findings (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		finding_title varchar(255) NOT NULL,
		source_id bigint(20) unsigned DEFAULT 0,
		signal_pattern_id bigint(20) unsigned DEFAULT 0,
		target_type varchar(80) DEFAULT 'general',
		target_id bigint(20) unsigned DEFAULT 0,
		finding_type varchar(80) DEFAULT 'neutral',
		finding_area varchar(120) DEFAULT '',
		signal_strength varchar(50) DEFAULT 'medium',
		repetition_level varchar(50) DEFAULT 'single',
		verification_status varchar(50) DEFAULT 'new',
		evidence_type varchar(80) DEFAULT 'text',
		evidence_excerpt longtext NULL,
		evidence_url text NULL,
		observed_at datetime NULL,
		reviewer_name varchar(190) DEFAULT '',
		reviewer_origin varchar(120) DEFAULT '',
		language varchar(50) DEFAULT '',
		related_collection_task_id bigint(20) unsigned DEFAULT 0,
		notes longtext NULL,
		created_at datetime NOT NULL,
		updated_at datetime NOT NULL,
		PRIMARY KEY  (id),
		KEY source_id (source_id),
		KEY signal_pattern_id (signal_pattern_id),
		KEY target_type (target_type),
		KEY target_id (target_id),
		KEY finding_type (finding_type),
		KEY finding_area (finding_area),
		KEY signal_strength (signal_strength),
		KEY repetition_level (repetition_level),
		KEY verification_status (verification_status),
		KEY evidence_type (evidence_type),
		KEY observed_at (observed_at),
		KEY related_collection_task_id (related_collection_task_id)
	) {$charset_collate};";

		$tables[] = "CREATE TABLE {$wpdb->prefix}toptour_ref_photo_evidence (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		evidence_title varchar(255) NOT NULL,
		source_id bigint(20) unsigned DEFAULT 0,
		finding_id bigint(20) unsigned DEFAULT 0,
		target_type varchar(80) DEFAULT 'general',
		target_id bigint(20) unsigned DEFAULT 0,
		photo_type varchar(80) DEFAULT 'guest_photo',
		comparison_category varchar(80) DEFAULT 'unknown',
		visual_area varchar(120) DEFAULT '',
		evidence_url text NULL,
		thumbnail_url text NULL,
		official_reference_url text NULL,
		guest_reference_url text NULL,
		observation_summary longtext NULL,
		visible_details longtext NULL,
		contradiction_note longtext NULL,
		verification_status varchar(50) DEFAULT 'new',
		signal_strength varchar(50) DEFAULT 'medium',
		observed_at datetime NULL,
		language varchar(50) DEFAULT '',
		related_collection_task_id bigint(20) unsigned DEFAULT 0,
		notes longtext NULL,
		created_at datetime NOT NULL,
		updated_at datetime NOT NULL,
		PRIMARY KEY  (id),
		KEY source_id (source_id),
		KEY finding_id (finding_id),
		KEY target_type (target_type),
		KEY target_id (target_id),
		KEY photo_type (photo_type),
		KEY comparison_category (comparison_category),
		KEY visual_area (visual_area),
		KEY verification_status (verification_status),
		KEY signal_strength (signal_strength),
		KEY observed_at (observed_at),
		KEY related_collection_task_id (related_collection_task_id)
	) {$charset_collate};";

		foreach ( $tables as $sql ) {
			dbDelta( $sql );
		}
	}

	/**
	 * Seed default signal patterns if not present.
	 *
	 * @return void
	 */
	public static function seed_signal_patterns() {
		global $wpdb;
		$table = $wpdb->prefix . 'toptour_ref_signal_patterns';
		$now = current_time( 'mysql' );
		$seeds = [
			[
				'pattern_key' => 'cleanliness_positive',
				'name' => 'Repeated cleanliness praise',
				'description' => 'Repeated positive references to cleanliness in guest reviews or verified feedback.',
				'pattern_type' => 'positive',
				'source_type' => 'review',
				'evidence_method' => 'review_excerpt',
				'default_weight_hint' => 'medium',
				'is_active' => 1,
			],
			[
				'pattern_key' => 'cleanliness_risk',
				'name' => 'Repeated cleanliness complaints',
				'description' => 'Repeated complaints or warnings about cleanliness in guest reviews or verified feedback.',
				'pattern_type' => 'risk',
				'source_type' => 'review',
				'evidence_method' => 'review_excerpt',
				'default_weight_hint' => 'high',
				'is_active' => 1,
			],
			[
				'pattern_key' => 'official_vs_guest_photo_contradiction',
				'name' => 'Official vs guest photo contradiction',
				'description' => 'Difference between official promotional photos and guest photos or videos.',
				'pattern_type' => 'contradiction',
				'source_type' => 'mixed',
				'evidence_method' => 'photo_comparison',
				'default_weight_hint' => 'high',
				'is_active' => 1,
			],
			[
				'pattern_key' => 'staff_positive',
				'name' => 'Repeated staff praise',
				'description' => 'Repeated positive references to staff behavior, helpfulness or professionalism.',
				'pattern_type' => 'positive',
				'source_type' => 'review',
				'evidence_method' => 'review_excerpt',
				'default_weight_hint' => 'medium',
				'is_active' => 1,
			],
			[
				'pattern_key' => 'noise_risk',
				'name' => 'Repeated noise complaints',
				'description' => 'Repeated complaints about noise, poor sound insulation or disturbing surroundings.',
				'pattern_type' => 'risk',
				'source_type' => 'review',
				'evidence_method' => 'review_excerpt',
				'default_weight_hint' => 'medium',
				'is_active' => 1,
			],
			[
				'pattern_key' => 'location_accessibility_uncertainty',
				'name' => 'Location or accessibility uncertainty',
				'description' => 'Unclear, inconsistent or contradictory information about location, access, transport or distance claims.',
				'pattern_type' => 'uncertainty',
				'source_type' => 'mixed',
				'evidence_method' => 'source_crosscheck',
				'default_weight_hint' => 'medium',
				'is_active' => 1,
			],
			[
				'pattern_key' => 'suspicious_review_similarity',
				'name' => 'Suspicious review similarity',
				'description' => 'Repeatedly similar review wording suggesting possible low-quality, copied or unreliable references.',
				'pattern_type' => 'source_quality',
				'source_type' => 'review',
				'evidence_method' => 'source_crosscheck',
				'default_weight_hint' => 'high',
				'is_active' => 1,
			],
			[
				'pattern_key' => 'guest_photo_positive_surprise',
				'name' => 'Guest photo positive surprise',
				'description' => 'Guest photo or video shows a better reality than expected from official presentation or written reviews.',
				'pattern_type' => 'positive',
				'source_type' => 'guest_photo',
				'evidence_method' => 'photo_comparison',
				'default_weight_hint' => 'medium',
				'is_active' => 1,
			],
		];

		foreach ( $seeds as $seed ) {
			$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE pattern_key = %s", $seed['pattern_key'] ) );
			if ( ! $exists ) {
				$wpdb->insert( $table, array_merge( $seed, [
					'created_at' => $now,
					'updated_at' => $now,
				] ) );
			}
		}
	}

	/**
	 * Seed default interests if not present.
	 *
	 * @return void
	 */
	public static function seed_interests() {
		global $wpdb;
		$table = $wpdb->prefix . 'toptour_ref_interests';
		$now = current_time( 'mysql' );
		$seeds = [
			[
				'interest_key' => 'hik_fly',
				'name' => 'Hik & Fly',
				'interest_type' => 'sport',
				'description' => 'Mountain hiking combined with free flight or paragliding context.',
			],
			[
				'interest_key' => 'paragliding',
				'name' => 'Paragliding',
				'interest_type' => 'sport',
				'description' => 'Free flight activity and local flying knowledge.',
			],
			[
				'interest_key' => 'hang_gliding',
				'name' => 'Hang gliding',
				'interest_type' => 'sport',
				'description' => 'Hang gliding activity, sites, pilots and safety context.',
			],
			[
				'interest_key' => 'wellness',
				'name' => 'Wellness',
				'interest_type' => 'wellness',
				'description' => 'Wellness services, spa support and regeneration.',
			],
			[
				'interest_key' => 'family_travel',
				'name' => 'Family travel',
				'interest_type' => 'tourism',
				'description' => 'Travel needs and services for families.',
			],
			[
				'interest_key' => 'senior_support',
				'name' => 'Senior support',
				'interest_type' => 'tourism',
				'description' => 'Travel and local support for seniors.',
			],
			[
				'interest_key' => 'local_food',
				'name' => 'Local food',
				'interest_type' => 'food',
				'description' => 'Local gastronomy, food experiences and suppliers.',
			],
			[
				'interest_key' => 'forest_bathing',
				'name' => 'Forest bathing',
				'interest_type' => 'nature',
				'description' => 'Shinrin-yoku, forest walks and nature-based regeneration.',
			],
			[
				'interest_key' => 'local_products',
				'name' => 'Local products',
				'interest_type' => 'local_product',
				'description' => 'Local producers, crafts and destination products.',
			],
			[
				'interest_key' => 'transport_support',
				'name' => 'Transport support',
				'interest_type' => 'transport',
				'description' => 'Local transport, transfers, logistics and mobility help.',
			],
			[
				'interest_key' => 'photo_documentation',
				'name' => 'Photo documentation',
				'interest_type' => 'marketing',
				'description' => 'Guest photos, local visual documentation and destination evidence.',
			],
			[
				'interest_key' => 'guest_feedback',
				'name' => 'Guest feedback',
				'interest_type' => 'hospitality',
				'description' => 'Collecting and interpreting guest experience and references.',
			],
			[
				'interest_key' => 'destination_marketing',
				'name' => 'Destination marketing',
				'interest_type' => 'marketing',
				'description' => 'Promotion, positioning and local destination communication.',
			],
		];

		foreach ( $seeds as $seed ) {
			$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE interest_key = %s", $seed['interest_key'] ) );
			if ( ! $exists ) {
				$wpdb->insert( $table, array_merge( $seed, [
					'is_active' => 1,
					'created_at' => $now,
					'updated_at' => $now,
				] ) );
			}
		}
	}

	/**
	 * Seed default mail templates if not present.
	 *
	 * @return void
	 */
	public static function seed_mail_templates() {
		require_once TOPTOUR_REF_PLUGIN_DIR . 'includes/class-mail-templates.php';
		Toptour_Ref_Mail_Templates::seed_templates();
	}

	/**
	 * Get stored DB schema version.
	 *
	 * @return string
	 */
	public static function get_schema_version() {
		return get_option( 'toptour_ref_db_version', '0.0.0' );
	}

	/**
	 * Update DB schema version to current.
	 *
	 * @return void
	 */
	public static function update_schema_version() {
		update_option( 'toptour_ref_db_version', TOPTOUR_REF_DB_VERSION );
	}

	/**
	 * Plugin deactivation hook callback.
	 *
	 * @return void
	 */
	public static function deactivate() {
		// Cleanup is minimal at this stage.
		// Database tables are NOT dropped on deactivation.
		// Capabilities remain for future reactivation.
		flush_rewrite_rules();
	}
}
