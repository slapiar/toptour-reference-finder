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

		$analysis['task_target_type'] = sanitize_text_field( (string) ( $task->target_type ?? 'general' ) );
		$analysis['task_target_id'] = absint( $task->target_id ?? 0 );
		$analysis['is_discovery_task'] = self::is_discovery_task( $task ) ? 1 : 0;
		$analysis['discovery_query_seeds'] = self::build_discovery_query_seeds( $task, $analysis );
		if ( empty( $analysis['discovery_query_seeds'] ) ) {
			$analysis['discovery_query_seeds'] = Toptour_Ref_Discovery_Provider::build_search_queries( $analysis );
		}
		$analysis['search_queries'] = $analysis['discovery_query_seeds'];
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
		$analysis['is_discovery_task'] = self::has_discovery_signal_text( $text ) && ( '' !== $destination_candidate || '' !== $facility_candidate || ! empty( $platform_hints ) || ! empty( $finding_area_candidates ) ) ? 1 : 0;
		$analysis['discovery_query_seeds'] = self::build_discovery_query_seeds_from_analysis( $analysis );
		$analysis['search_queries'] = ! empty( $analysis['discovery_query_seeds'] )
			? $analysis['discovery_query_seeds']
			: Toptour_Ref_Discovery_Provider::build_search_queries( $analysis );

		return $analysis;
	}

	public static function detect_destination_candidate( $text ) {
		$text_lc = self::normalize_text( $text );
		if ( false !== strpos( $text_lc, 'liptov' ) ) {
			return 'Liptov';
		}
		if ( false !== strpos( $text_lc, 'sardinia' ) || false !== strpos( $text_lc, 'sardegna' ) || false !== strpos( $text_lc, 'sardinie' ) ) {
			return 'Sardinia';
		}
		return '';
	}

	public static function detect_facility_candidate( $text ) {
		$text_lc = self::normalize_text( $text );
		if ( false !== strpos( $text_lc, 'hotel' ) ) {
			return 'Hotel';
		}
		if ( false !== strpos( $text_lc, 'resort' ) || false !== strpos( $text_lc, 'rezort' ) ) {
			return 'Resort';
		}
		if ( false !== strpos( $text_lc, 'villaggio' ) ) {
			return 'Villaggio';
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
		if ( false !== strpos( $text_lc, 'hotel' ) || false !== strpos( $text_lc, 'penzion' ) || false !== strpos( $text_lc, 'penzión' ) || false !== strpos( $text_lc, 'resort' ) || false !== strpos( $text_lc, 'rezort' ) || false !== strpos( $text_lc, 'villaggio' ) || false !== strpos( $text_lc, 'zariadenie' ) ) {
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
		if ( false !== strpos( $text_lc, 'all inclusive' ) ) {
			$areas[] = 'food';
		}
		if ( false !== strpos( $text_lc, 'plaz' ) || false !== strpos( $text_lc, 'beach' ) ) {
			$areas[] = 'beach';
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

	public static function is_discovery_task( $task ) {
		if ( ! $task ) {
			return false;
		}

		$target_type = sanitize_text_field( (string) ( $task->target_type ?? '' ) );
		$target_id = absint( $task->target_id ?? 0 );
		if ( 'facility' !== $target_type || $target_id > 0 ) {
			return false;
		}

		$text = trim(
			(string) ( $task->task_title ?? '' ) . ' ' .
			(string) ( $task->query_text ?? '' ) . ' ' .
			(string) ( $task->source_hint ?? '' )
		);

		return self::has_discovery_signal_text( $text );
	}

	public static function build_discovery_query_seeds( $task, $analysis = [] ) {
		$text = trim(
			(string) ( $task->task_title ?? '' ) . ' ' .
			(string) ( $task->query_text ?? '' ) . ' ' .
			(string) ( $task->source_hint ?? '' )
		);

		return self::build_discovery_query_seeds_from_text( $text, $analysis );
	}

	private static function build_discovery_query_seeds_from_analysis( $analysis ) {
		$text = trim(
			(string) ( $analysis['task_title'] ?? '' ) . ' ' .
			(string) ( $analysis['query_text'] ?? '' ) . ' ' .
			(string) ( $analysis['source_hint'] ?? '' )
		);

		return self::build_discovery_query_seeds_from_text( $text, $analysis );
	}

	private static function build_discovery_query_seeds_from_text( $text, $analysis = [] ) {
		$text_lc = self::normalize_text( $text );
		$destination = self::detect_destination_candidate( $text );
		$facility = self::detect_facility_candidate( $text );

		if ( 'sardinia' === self::normalize_text( $destination ) || false !== strpos( $text_lc, 'sardinia' ) || false !== strpos( $text_lc, 'sardegna' ) ) {
			return [
				'Sardinia resort complaints',
				'Sardinia hotel bad reviews',
				'Sardegna hotel recensioni negative',
				'Sardinia all inclusive bad reviews',
				'Sardinia hotel hidden beach fees',
				'Sardinia resort beach service fee complaints',
				'Sardinia hotel not 5 star reviews',
				'Sardinia resort poor service reviews',
				'Sardinia villaggio complaints',
				'Costa Smeralda hotel bad reviews',
				'San Teodoro resort negative reviews',
				'Tripadvisor Sardinia resort complaints',
				'Booking Sardinia hotel cleanliness complaints',
				'Ostrovok Sardinia hotel reviews',
			];
		}

		$destination_label = $destination !== '' ? $destination : ( ! empty( $analysis['destination_candidate'] ) ? sanitize_text_field( $analysis['destination_candidate'] ) : 'working destination' );
		$focus = $facility !== '' ? strtolower( sanitize_text_field( $facility ) ) : 'hotel resort';

		$seeds = [
			$destination_label . ' ' . $focus . ' complaints',
			$destination_label . ' ' . $focus . ' bad reviews',
			$destination_label . ' all inclusive complaints',
			$destination_label . ' hidden beach fees',
			$destination_label . ' poor service reviews',
			'Tripadvisor ' . $destination_label . ' ' . $focus . ' reviews',
			'Booking ' . $destination_label . ' ' . $focus . ' complaints',
			'Ostrovok ' . $destination_label . ' ' . $focus . ' reviews',
		];

		return array_values( array_unique( array_filter( array_map( 'sanitize_text_field', $seeds ) ) ) );
	}

	private static function has_discovery_signal_text( $text ) {
		$text_lc = self::normalize_text( $text );
		$signals = [
			'sardinia',
			'sardegna',
			'sardinie',
			'recenzie',
			'review',
			'complaint',
			'complaints',
			'negative',
			'negativ',
			'bad reviews',
			'all inclusive',
			'beach',
			'plaz',
			'resort',
			'rezort',
			'hotel',
			'villaggio',
		];

		foreach ( $signals as $signal ) {
			if ( false !== strpos( $text_lc, $signal ) ) {
				return true;
			}
		}

		return false;
	}

	private static function seed_discovery_artifacts( $task, $analysis, $run_id ) {
		$task_id = (int) ( $task->id ?? 0 );
		if ( $task_id <= 0 ) {
			return [
				'query_seed_count' => 0,
				'source_count' => 0,
				'facility_count' => 0,
				'finding_count' => 0,
				'photo_count' => 0,
				'destination_id' => 0,
			];
		}

		$query_seeds = is_array( $analysis['search_queries'] ?? null ) ? $analysis['search_queries'] : [];
		if ( empty( $query_seeds ) ) {
			$query_seeds = self::build_discovery_query_seeds( $task, $analysis );
		}

		$destination_name = sanitize_text_field( $analysis['destination_candidate'] ?? '' );
		$destination_id = 0;
		if ( '' !== $destination_name ) {
			$destination_id = (int) self::create_destination_from_candidate( $destination_name );
		}

		$facility_specs = self::get_discovery_facility_specs( $analysis );
		$facility_ids = [];
		foreach ( $facility_specs as $facility_spec ) {
			$facility_id = self::upsert_discovery_facility_candidate( $task_id, $run_id, $facility_spec, $destination_id );
			if ( $facility_id > 0 ) {
				$facility_ids[] = $facility_id;
			}
		}

		$source_specs = self::get_discovery_source_specs( $analysis, $query_seeds );
		$source_ids = [];
		foreach ( $source_specs as $index => $source_spec ) {
			$linked_facility_id = ! empty( $facility_ids ) ? (int) $facility_ids[ $index % count( $facility_ids ) ] : 0;
			$source_id = self::upsert_discovery_source_candidate( $task_id, $run_id, $source_spec, $destination_id, $linked_facility_id, $query_seeds );
			if ( $source_id > 0 ) {
				$source_ids[] = $source_id;
			}
		}

		$finding_specs = self::get_discovery_finding_specs();
		$finding_ids = [];
		foreach ( $finding_specs as $index => $finding_spec ) {
			$source_id = ! empty( $source_ids ) ? (int) $source_ids[ $index % count( $source_ids ) ] : 0;
			$facility_id = ! empty( $facility_ids ) ? (int) $facility_ids[ $index % count( $facility_ids ) ] : 0;
			$finding_id = self::upsert_discovery_finding_candidate( $task_id, $run_id, $finding_spec, $source_id, $facility_id, $destination_id, $query_seeds );
			if ( $finding_id > 0 ) {
				$finding_ids[] = $finding_id;
			}
		}

		$photo_ids = [];
		foreach ( $source_ids as $index => $source_id ) {
			$facility_id = ! empty( $facility_ids ) ? (int) $facility_ids[ $index % count( $facility_ids ) ] : 0;
			$finding_id = ! empty( $finding_ids ) ? (int) $finding_ids[ $index % count( $finding_ids ) ] : 0;
			$photo_id = self::upsert_discovery_photo_candidate( $task_id, $run_id, $source_id, $finding_id, $facility_id, $destination_id );
			if ( $photo_id > 0 ) {
				$photo_ids[] = $photo_id;
			}
		}

		return [
			'query_seed_count' => count( $query_seeds ),
			'source_count' => count( $source_ids ),
			'facility_count' => count( $facility_ids ),
			'finding_count' => count( $finding_ids ),
			'photo_count' => count( $photo_ids ),
			'destination_id' => $destination_id,
		];
	}

	private static function get_discovery_facility_specs( $analysis ) {
		$text = trim(
			(string) ( $analysis['task_title'] ?? '' ) . ' ' .
			(string) ( $analysis['query_text'] ?? '' ) . ' ' .
			(string) ( $analysis['source_hint'] ?? '' )
		);

		if ( false !== strpos( self::normalize_text( $text ), 'sardinia' ) || false !== strpos( self::normalize_text( $text ), 'sardegna' ) ) {
			return [
				[ 'name' => "Mangia's Sardinia Resort", 'facility_type' => 'resort', 'region' => 'Sardinia' ],
				[ 'name' => "Mangia's Santa Teresa Sardinia", 'facility_type' => 'resort', 'region' => 'Sardinia' ],
			];
		}

		$destination = sanitize_text_field( $analysis['destination_candidate'] ?? 'Working destination' );
		return [
			[ 'name' => $destination . ' Resort Candidate', 'facility_type' => 'resort', 'region' => $destination ],
			[ 'name' => $destination . ' Hotel Candidate', 'facility_type' => 'hotel', 'region' => $destination ],
		];
	}

	private static function get_discovery_source_specs( $analysis, $query_seeds ) {
		$destination = sanitize_text_field( $analysis['destination_candidate'] ?? 'Sardinia' );
		$first_query = ! empty( $query_seeds ) ? sanitize_text_field( $query_seeds[0] ) : $destination . ' complaints';

		return [
			[ 'title' => 'TripAdvisor ' . $destination . ' complaints candidate', 'platform' => 'TripAdvisor', 'url' => 'https://www.tripadvisor.com/', 'query' => $first_query ],
			[ 'title' => 'Booking ' . $destination . ' complaints candidate', 'platform' => 'Booking.com', 'url' => 'https://www.booking.com/', 'query' => ! empty( $query_seeds[1] ) ? sanitize_text_field( $query_seeds[1] ) : $first_query ],
			[ 'title' => 'Ostrovok ' . $destination . ' reviews candidate', 'platform' => 'Ostrovok', 'url' => 'https://www.ostrovok.ru/', 'query' => ! empty( $query_seeds[2] ) ? sanitize_text_field( $query_seeds[2] ) : $first_query ],
		];
	}

	private static function get_discovery_finding_specs() {
		return [
			[ 'category' => 'star_rating_mismatch', 'title' => 'Candidate: star rating mismatch', 'finding_area' => 'service_quality' ],
			[ 'category' => 'weak_all_inclusive', 'title' => 'Candidate: weak all inclusive', 'finding_area' => 'food' ],
			[ 'category' => 'hidden_beach_fees', 'title' => 'Candidate: hidden beach fees', 'finding_area' => 'beach' ],
			[ 'category' => 'poor_internal_logistics', 'title' => 'Candidate: poor internal logistics', 'finding_area' => 'accessibility' ],
			[ 'category' => 'weak_gastronomy', 'title' => 'Candidate: weak gastronomy', 'finding_area' => 'food' ],
			[ 'category' => 'poor_service', 'title' => 'Candidate: poor service', 'finding_area' => 'service_quality' ],
			[ 'category' => 'outdated_rooms', 'title' => 'Candidate: outdated rooms', 'finding_area' => 'room' ],
			[ 'category' => 'misleading_photos', 'title' => 'Candidate: misleading photos', 'finding_area' => 'photos' ],
			[ 'category' => 'slow_wifi', 'title' => 'Candidate: slow wifi', 'finding_area' => 'other' ],
			[ 'category' => 'language_barrier', 'title' => 'Candidate: language barrier', 'finding_area' => 'other' ],
			[ 'category' => 'noisy_family_resort', 'title' => 'Candidate: noisy family resort', 'finding_area' => 'noise' ],
			[ 'category' => 'paid_towels', 'title' => 'Candidate: paid towels', 'finding_area' => 'price_value' ],
			[ 'category' => 'paid_front_row_sunbeds', 'title' => 'Candidate: paid front row sunbeds', 'finding_area' => 'beach' ],
		];
	}

	private static function upsert_discovery_facility_candidate( $task_id, $run_id, $facility_spec, $destination_id ) {
		$name = sanitize_text_field( $facility_spec['name'] ?? '' );
		if ( '' === $name ) {
			return 0;
		}

		$existing = self::find_existing_facility( $name );
		$data = [
			'name' => $name,
			'slug' => sanitize_title( $name ),
			'facility_type' => sanitize_text_field( $facility_spec['facility_type'] ?? 'other' ),
			'country' => 'Italy',
			'region' => sanitize_text_field( $facility_spec['region'] ?? 'Sardinia' ),
			'city' => '',
			'address' => '',
			'website_url' => '',
			'official_source_url' => '',
			'status' => 'draft',
			'notes' => sprintf( 'generated_from_task_id:%d generated_from_run_id:%d discovery_candidate pending_review destination_id:%d', $task_id, $run_id, $destination_id ),
		];
		$validated = Toptour_Ref_Facilities::validate_facility_data( $data );
		if ( true !== $validated ) {
			return 0;
		}

		if ( $existing ) {
			Toptour_Ref_Facilities::update_facility( (int) $existing->id, $data );
			return (int) $existing->id;
		}

		return (int) Toptour_Ref_Facilities::create_facility( $data );
	}

	private static function upsert_discovery_source_candidate( $task_id, $run_id, $source_spec, $destination_id, $facility_id, $query_seeds ) {
		global $wpdb;
		$source_url = esc_url_raw( $source_spec['url'] ?? '' );
		$source_title = sanitize_text_field( $source_spec['title'] ?? '' );
		if ( '' === $source_title ) {
			return 0;
		}

		$existing = null;
		if ( '' !== $source_url ) {
			$existing = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Toptour_Ref_Reference_Sources::get_table_name() . ' WHERE collection_task_id = %d AND source_url = %s ORDER BY id DESC LIMIT 1', absint( $task_id ), $source_url ) );
		}

		$source_data = [
			'source_title' => $source_title,
			'source_url' => $source_url,
			'source_url_raw' => $source_url,
			'source_platform' => sanitize_text_field( $source_spec['platform'] ?? '' ),
			'source_type' => 'review',
			'source_origin' => 'manual_discovery',
			'target_type' => 'facility',
			'target_id' => absint( $facility_id ),
			'collection_task_id' => absint( $task_id ),
			'language' => '',
			'captured_at' => '',
			'source_date' => '',
			'external_rating' => '',
			'external_review_count' => 0,
			'credibility_level' => 'unknown',
			'credibility_reason' => 'generated_from_task_id:' . absint( $task_id ) . ' generated_from_run_id:' . absint( $run_id ),
			'credibility_updated_at' => '',
			'verification_method' => 'not_verified',
			'verification_notes' => 'pending_review discovery candidate',
			'last_verified_at' => '',
			'suggested_credibility_level' => 'unknown',
			'suggestion_reason' => 'generated_from_task_id:' . absint( $task_id ) . '; query_seed:' . sanitize_text_field( (string) ( $source_spec['query'] ?? '' ) ),
			'suggestion_status' => 'manager_review',
			'suggestion_created_at' => '',
			'suggestion_resolved_at' => '',
			'suggestion_reviewed_by' => 0,
			'search_priority' => 'high',
			'next_action' => 'review_source',
			'validation_status' => 'new',
			'access_status' => 'unknown',
			'notes' => 'generated_from_task_id:' . absint( $task_id ) . ' generated_from_run_id:' . absint( $run_id ) . ' pending_review',
		];

		$validated = Toptour_Ref_Reference_Sources::validate_source_data( $source_data );
		if ( true !== $validated ) {
			return 0;
		}

		if ( $existing ) {
			Toptour_Ref_Reference_Sources::update_source( (int) $existing->id, $source_data );
			return (int) $existing->id;
		}

		return (int) Toptour_Ref_Reference_Sources::create_source( $source_data );
	}

	private static function upsert_discovery_finding_candidate( $task_id, $run_id, $finding_spec, $source_id, $facility_id, $destination_id, $query_seeds ) {
		global $wpdb;
		$category = sanitize_key( $finding_spec['category'] ?? '' );
		$title = sanitize_text_field( $finding_spec['title'] ?? '' );
		if ( '' === $category || '' === $title ) {
			return 0;
		}

		$hash = md5( 'discovery:' . $task_id . ':' . $category . ':' . $source_id . ':' . $facility_id . ':' . $destination_id );
		$existing = $wpdb->get_row( $wpdb->prepare( 'SELECT id FROM ' . Toptour_Ref_Findings::get_table_name() . ' WHERE hash = %s ORDER BY id DESC LIMIT 1', $hash ) );

		$finding_data = [
			'finding_title' => $title,
			'task_id' => absint( $task_id ),
			'run_id' => absint( $run_id ),
			'source_url' => '',
			'source_title' => $title,
			'source_type' => 'review',
			'excerpt' => 'Working discovery finding candidate generated from task #' . absint( $task_id ) . '.',
			'detected_sentiment' => 'negative',
			'review_published_at' => '',
			'analysis_performed_at' => current_time( 'mysql' ),
			'source_detected_at' => current_time( 'mysql' ),
			'source_last_checked_at' => current_time( 'mysql' ),
			'reference_language' => 'en',
			'reference_type' => 'guest_review',
			'analysis_summary' => 'candidate_category:' . $category . ' pending_review generated_from_task_id:' . absint( $task_id ),
			'analysis_status' => 'needs_review',
			'confidence_score' => null,
			'destination_mapping_note' => 'working_discovery_destination_id:' . absint( $destination_id ),
			'poi_extraction_note' => '',
			'offer_relation_note' => '',
			'poi_candidate_id' => 0,
			'destination_id' => absint( $destination_id ),
			'supplier_id' => absint( $facility_id ),
			'offer_id' => 0,
			'hash' => $hash,
			'status' => 'pending_review',
			'found_at' => current_time( 'mysql' ),
			'reviewed_by' => 0,
			'reviewed_at' => '',
			'source_id' => absint( $source_id ),
			'signal_pattern_id' => 0,
			'target_type' => absint( $facility_id ) > 0 ? 'facility' : 'destination',
			'target_id' => absint( $facility_id ) > 0 ? absint( $facility_id ) : absint( $destination_id ),
			'finding_type' => 'risk',
			'finding_area' => sanitize_text_field( $finding_spec['finding_area'] ?? 'other' ),
			'signal_strength' => 'medium',
			'repetition_level' => 'repeated',
			'verification_status' => 'needs_verification',
			'evidence_type' => 'review_excerpt',
			'evidence_excerpt' => 'Query seeds: ' . implode( ' | ', array_slice( array_map( 'sanitize_text_field', (array) $query_seeds ), 0, 3 ) ),
			'evidence_url' => '',
			'observed_at' => current_time( 'mysql' ),
			'reviewer_name' => '',
			'reviewer_origin' => '',
			'language' => 'en',
			'related_collection_task_id' => absint( $task_id ),
			'notes' => 'generated_from_task_id:' . absint( $task_id ) . ' generated_from_run_id:' . absint( $run_id ) . ' pending_review',
		];

		$sanitized = Toptour_Ref_Findings::sanitize_finding_data( $finding_data );
		$validated = Toptour_Ref_Findings::validate_finding_data( $sanitized );
		if ( true !== $validated ) {
			return 0;
		}

		if ( $existing ) {
			return (int) $existing->id;
		}

		return (int) Toptour_Ref_Findings::create_finding( $sanitized );
	}

	private static function upsert_discovery_photo_candidate( $task_id, $run_id, $source_id, $finding_id, $facility_id, $destination_id ) {
		global $wpdb;
		$source_id = absint( $source_id );
		if ( $source_id <= 0 ) {
			return 0;
		}

		$photo_url = 'https://example.com/discovery/photo/' . absint( $task_id ) . '/' . absint( $source_id );
		$existing = $wpdb->get_row( $wpdb->prepare( 'SELECT id FROM ' . Toptour_Ref_Photo_Evidence::get_table_name() . ' WHERE related_collection_task_id = %d AND source_id = %d AND evidence_url = %s ORDER BY id DESC LIMIT 1', absint( $task_id ), $source_id, $photo_url ) );

		$photo_data = Toptour_Ref_Photo_Evidence::sanitize_photo_evidence_data(
			[
				'evidence_title' => 'Photo evidence candidate for task #' . absint( $task_id ),
				'source_id' => $source_id,
				'finding_id' => absint( $finding_id ),
				'target_type' => absint( $facility_id ) > 0 ? 'facility' : 'destination',
				'target_id' => absint( $facility_id ) > 0 ? absint( $facility_id ) : absint( $destination_id ),
				'photo_type' => 'guest_photo',
				'comparison_category' => 'unknown',
				'visual_area' => 'other',
				'evidence_url' => $photo_url,
				'thumbnail_url' => '',
				'official_reference_url' => '',
				'guest_reference_url' => '',
				'observation_summary' => 'Photo evidence candidate linked to discovery task #' . absint( $task_id ) . '.',
				'visible_details' => 'pending visual review',
				'contradiction_note' => '',
				'verification_status' => 'new',
				'signal_strength' => 'weak',
				'observed_at' => current_time( 'mysql' ),
				'language' => '',
				'related_collection_task_id' => absint( $task_id ),
				'notes' => 'photo evidence candidate pending visual review generated_from_task_id:' . absint( $task_id ) . ' generated_from_run_id:' . absint( $run_id ),
			]
		);

		$validated = Toptour_Ref_Photo_Evidence::validate_photo_evidence_data( $photo_data );
		if ( true !== $validated ) {
			return 0;
		}

		if ( $existing ) {
			Toptour_Ref_Photo_Evidence::update_photo_evidence( (int) $existing->id, $photo_data );
			return (int) $existing->id;
		}

		return (int) Toptour_Ref_Photo_Evidence::create_photo_evidence( $photo_data );
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

		$task = Toptour_Ref_Collection_Tasks::get_task( $collection_task_id );
		if ( $task ) {
			$seed_summary = self::seed_discovery_artifacts( $task, $analysis, $run_id );
			if ( is_array( $seed_summary ) ) {
				global $wpdb;
				$wpdb->update(
					Toptour_Ref_Discovery_Runs::get_table_name(),
					[
						'run_notes' => sprintf(
							'query_seed_count=%d source_count=%d facility_count=%d finding_count=%d photo_count=%d destination_id=%d',
							(int) ( $seed_summary['query_seed_count'] ?? 0 ),
							(int) ( $seed_summary['source_count'] ?? 0 ),
							(int) ( $seed_summary['facility_count'] ?? 0 ),
							(int) ( $seed_summary['finding_count'] ?? 0 ),
							(int) ( $seed_summary['photo_count'] ?? 0 ),
							(int) ( $seed_summary['destination_id'] ?? 0 )
						),
						'updated_at' => current_time( 'mysql' ),
					],
					[ 'id' => absint( $run_id ) ]
				);
			}
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
