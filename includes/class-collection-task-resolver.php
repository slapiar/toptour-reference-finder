<?php
/**
 * Collection Task Resolver class.
 *
 * Rule-based analyzer for collection tasks.
 *
 * @package Toptour_Ref
 * @version 0.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Toptour_Ref_Collection_Task_Resolver {

	public static function analyze_task( $task ) {
		if ( ! $task ) {
			return [];
		}

		$analysis = self::analyze_text(
			(string) ( $task->task_title ?? '' ),
			(string) ( $task->query_text ?? '' ),
			(string) ( $task->source_hint ?? '' )
		);

		$analysis['collection_task_id'] = (int) ( $task->id ?? 0 );
		return $analysis;
	}

	public static function analyze_text( $task_title, $query_text, $source_hint = '' ) {
		$text = trim( $task_title . ' ' . $query_text . ' ' . $source_hint );
		$target_type = self::detect_target_type( $text );
		$destination_candidate = self::detect_destination_candidate( $text );
		$facility_candidate = self::detect_facility_candidate( $text );
		$expected_source_type = self::detect_expected_source_type( $text );
		$platform_hints = self::detect_platform_hints( $text );
		$interest_candidates = self::detect_interest_candidates( $text );
		$finding_area_candidates = self::detect_finding_area_candidates( $text );

		$existing_destination = $destination_candidate ? self::find_existing_destination( $destination_candidate ) : null;
		$existing_facility = $facility_candidate ? self::find_existing_facility( $facility_candidate ) : null;

		$resolved_target_type = 'general';
		$resolved_target_id = 0;
		$resolved_target_label = '';

		if ( $existing_destination ) {
			$resolved_target_type = 'destination';
			$resolved_target_id = (int) $existing_destination->id;
			$resolved_target_label = (string) $existing_destination->name;
		} elseif ( $existing_facility ) {
			$resolved_target_type = 'facility';
			$resolved_target_id = (int) $existing_facility->id;
			$resolved_target_label = (string) $existing_facility->name;
		} elseif ( in_array( $target_type, [ 'destination', 'facility' ], true ) ) {
			$resolved_target_type = $target_type;
			$resolved_target_label = $target_type === 'destination' ? $destination_candidate : $facility_candidate;
		}

		$analysis = [
			'task_title' => sanitize_text_field( $task_title ),
			'query_text' => sanitize_textarea_field( $query_text ),
			'source_hint' => sanitize_textarea_field( $source_hint ),
			'target_type' => $target_type,
			'resolved_target_type' => $resolved_target_type,
			'resolved_target_id' => $resolved_target_id,
			'resolved_target_label' => sanitize_text_field( $resolved_target_label ),
			'destination_candidate' => sanitize_text_field( $destination_candidate ),
			'facility_candidate' => sanitize_text_field( $facility_candidate ),
			'destination_exists' => $existing_destination ? 1 : 0,
			'facility_exists' => $existing_facility ? 1 : 0,
			'expected_source_type' => $expected_source_type,
			'platform_hints' => $platform_hints,
			'interest_candidates' => $interest_candidates,
			'finding_area_candidates' => $finding_area_candidates,
		];

		$analysis['missing_fields'] = self::detect_missing_fields( $analysis );
		$analysis['search_queries'] = Toptour_Ref_Discovery_Provider::build_search_queries( $analysis );

		return $analysis;
	}

	public static function detect_destination_candidate( $text ) {
		$text_lc = self::normalize_text( $text );
		if ( false !== strpos( $text_lc, 'liptov' ) ) {
			return 'Liptov';
		}
		return '';
	}

	public static function detect_facility_candidate( $text ) {
		$text_lc = self::normalize_text( $text );
		if ( false !== strpos( $text_lc, 'hotel' ) ) {
			return 'Hotel';
		}
		if ( false !== strpos( $text_lc, 'penzion' ) ) {
			return 'Penzion';
		}
		return '';
	}

	public static function detect_target_type( $text ) {
		$text_lc = self::normalize_text( $text );
		if ( false !== strpos( $text_lc, 'region' ) || false !== strpos( $text_lc, 'destinacia' ) ) {
			return 'destination';
		}
		if ( false !== strpos( $text_lc, 'hotel' ) || false !== strpos( $text_lc, 'penzion' ) || false !== strpos( $text_lc, 'penzión' ) ) {
			return 'facility';
		}
		return 'general';
	}

	public static function detect_expected_source_type( $text ) {
		$text_lc = self::normalize_text( $text );
		if ( false !== strpos( $text_lc, 'recenzie' ) ) {
			return 'review';
		}
		if ( false !== strpos( $text_lc, 'video' ) || false !== strpos( $text_lc, 'videa' ) ) {
			return 'video';
		}
		if ( false !== strpos( $text_lc, 'fotky' ) || false !== strpos( $text_lc, 'fotografie' ) || false !== strpos( $text_lc, 'hostovske fotky' ) ) {
			return 'mixed';
		}
		return 'mixed';
	}

	public static function detect_interest_candidates( $text ) {
		$text_lc = self::normalize_text( $text );
		$interests = [];

		if ( false !== strpos( $text_lc, 'rodin' ) || false !== strpos( $text_lc, 'deti' ) ) {
			$interests[] = 'family_travel';
		}
		if ( false !== strpos( $text_lc, 'senior' ) ) {
			$interests[] = 'senior_support';
		}

		return array_values( array_unique( $interests ) );
	}

	public static function detect_platform_hints( $text ) {
		$text_lc = self::normalize_text( $text );
		$hints = [];

		if ( false !== strpos( $text_lc, 'google' ) ) {
			$hints[] = 'Google';
		}
		if ( false !== strpos( $text_lc, 'booking' ) ) {
			$hints[] = 'Booking';
		}
		if ( false !== strpos( $text_lc, 'tripadvisor' ) ) {
			$hints[] = 'TripAdvisor';
		}

		return array_values( array_unique( $hints ) );
	}

	public static function detect_finding_area_candidates( $text ) {
		$text_lc = self::normalize_text( $text );
		$areas = [];

		if ( false !== strpos( $text_lc, 'cistot' ) ) {
			$areas[] = 'cleanliness';
		}
		if ( false !== strpos( $text_lc, 'hluk' ) || false !== strpos( $text_lc, 'hlucnost' ) ) {
			$areas[] = 'noise';
		}
		if ( false !== strpos( $text_lc, 'strava' ) || false !== strpos( $text_lc, 'jedlo' ) || false !== strpos( $text_lc, 'ranajky' ) ) {
			$areas[] = 'food';
		}
		if ( false !== strpos( $text_lc, 'dostupnost' ) ) {
			$areas[] = 'accessibility';
		}
		if ( false !== strpos( $text_lc, 'doprav' ) ) {
			$areas[] = 'transport';
		}
		if ( false !== strpos( $text_lc, 'fotky' ) || false !== strpos( $text_lc, 'fotografie' ) || false !== strpos( $text_lc, 'hostovske fotky' ) ) {
			$areas[] = 'photos';
		}

		return array_values( array_unique( $areas ) );
	}

	public static function detect_missing_fields( $analysis ) {
		$missing = [];

		if ( empty( $analysis['destination_candidate'] ) ) {
			$missing[] = [
				'field_key' => 'destination_name',
				'field_label' => 'Nazov destinacie alebo regionu',
				'field_type' => 'text',
				'is_required' => 1,
				'field_status' => 'missing',
				'help_text' => 'Uvedte lokalitu, pre ktoru chcete hladat referencne zdroje.',
			];
		}

		$text_lc = self::normalize_text( (string) ( $analysis['query_text'] ?? '' ) );
		$has_stay_word = false !== strpos( $text_lc, 'pobyt' ) || false !== strpos( $text_lc, 'rodin' ) || false !== strpos( $text_lc, 'senior' );
		if ( ! $has_stay_word ) {
			$missing[] = [
				'field_key' => 'stay_type',
				'field_label' => 'Typ pobytu',
				'field_type' => 'text',
				'is_required' => 0,
				'field_status' => 'missing',
				'help_text' => 'Napr. rodinny pobyt, wellness, aktivna dovolenka.',
			];
		}

		if ( false === strpos( $text_lc, 'jazyk' ) && false === strpos( $text_lc, 'sk' ) && false === strpos( $text_lc, 'en' ) ) {
			$missing[] = [
				'field_key' => 'source_languages',
				'field_label' => 'Preferovane jazyky zdrojov',
				'field_type' => 'text',
				'is_required' => 0,
				'field_status' => 'missing',
				'help_text' => 'Napriklad sk, en, de, pl.',
			];
		}

		return $missing;
	}

	public static function find_existing_destination( $candidate_name ) {
		global $wpdb;
		$table = Toptour_Ref_Destinations::get_table_name();
		$like = '%' . $wpdb->esc_like( sanitize_text_field( $candidate_name ) ) . '%';
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE status != %s AND name LIKE %s ORDER BY id ASC LIMIT 1", 'archived', $like ) );
	}

	public static function find_existing_facility( $candidate_name ) {
		global $wpdb;
		$table = Toptour_Ref_Facilities::get_table_name();
		$like = '%' . $wpdb->esc_like( sanitize_text_field( $candidate_name ) ) . '%';
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE status != %s AND name LIKE %s ORDER BY id ASC LIMIT 1", 'archived', $like ) );
	}

	public static function find_existing_interest( $candidate_key_or_name ) {
		$by_key = Toptour_Ref_Interests::get_interest_by_key( sanitize_key( $candidate_key_or_name ) );
		if ( $by_key && (int) $by_key->is_active === 1 ) {
			return $by_key;
		}

		global $wpdb;
		$table = Toptour_Ref_Interests::get_table_name();
		$like = '%' . $wpdb->esc_like( sanitize_text_field( $candidate_key_or_name ) ) . '%';
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE is_active = 1 AND name LIKE %s ORDER BY id ASC LIMIT 1", $like ) );
	}

	public static function create_destination_from_candidate( $candidate_name ) {
		$name = sanitize_text_field( $candidate_name );
		if ( '' === $name ) {
			return false;
		}

		$existing = self::find_existing_destination( $name );
		if ( $existing ) {
			return (int) $existing->id;
		}

		$data = [
			'name'             => $name,
			'slug'             => sanitize_title( $name ),
			'country'          => '',
			'region'           => '',
			'destination_type' => 'other',
			'seasonality'      => '',
			'description'      => '',
			'notes'            => 'Vytvorene z Discovery Resolver workflow.',
			'status'           => 'draft',
		];

		$valid = Toptour_Ref_Destinations::validate_destination_data( $data );
		if ( true !== $valid ) {
			return false;
		}

		return Toptour_Ref_Destinations::create_destination( $data );
	}

	public static function create_discovery_run( $collection_task_id, $analysis ) {
		$run_data = [
			'collection_task_id'     => absint( $collection_task_id ),
			'run_title'              => 'Discovery run pre task #' . absint( $collection_task_id ),
			'input_summary'          => $analysis,
			'resolved_target_type'   => $analysis['resolved_target_type'] ?? 'general',
			'resolved_target_id'     => absint( $analysis['resolved_target_id'] ?? 0 ),
			'resolved_target_label'  => $analysis['resolved_target_label'] ?? '',
			'detected_destination'   => $analysis['destination_candidate'] ?? '',
			'detected_facility'      => $analysis['facility_candidate'] ?? '',
			'detected_interests'     => $analysis['interest_candidates'] ?? [],
			'detected_finding_areas' => $analysis['finding_area_candidates'] ?? [],
			'missing_fields'         => $analysis['missing_fields'] ?? [],
			'search_queries'         => $analysis['search_queries'] ?? [],
			'discovery_provider'     => 'manual',
			'run_status'             => empty( $analysis['missing_fields'] ) ? 'ready' : 'needs_input',
			'run_notes'              => '',
		];

		$run_id = Toptour_Ref_Discovery_Runs::create_run( $run_data );
		if ( ! $run_id ) {
			return false;
		}

		return (int) $run_id;
	}

	public static function apply_resolution_to_task( $task_id, $resolution ) {
		global $wpdb;
		$table = Toptour_Ref_Collection_Tasks::get_table_name();

		$target_type = sanitize_text_field( $resolution['target_type'] ?? 'general' );
		if ( ! in_array( $target_type, Toptour_Ref_Collection_Tasks::get_allowed_target_types(), true ) ) {
			$target_type = 'general';
		}

		$expected_source_type = sanitize_text_field( $resolution['expected_source_type'] ?? '' );
		if ( ! in_array( $expected_source_type, Toptour_Ref_Collection_Tasks::get_allowed_source_types(), true ) ) {
			$expected_source_type = '';
		}

		$result = $wpdb->update(
			$table,
			[
				'target_type'          => $target_type,
				'target_id'            => absint( $resolution['target_id'] ?? 0 ),
				'expected_source_type' => $expected_source_type,
				'updated_at'           => current_time( 'mysql' ),
			],
			[ 'id' => absint( $task_id ) ]
		);

		return $result !== false;
	}

	private static function normalize_text( $text ) {
		$text = strtolower( (string) $text );
		return strtolower( remove_accents( $text ) );
	}
}
