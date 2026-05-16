<?php
/**
 * Discovery provider abstraction class.
 *
 * Provides controlled discovery behaviors without automatic web scraping.
 *
 * @package Toptour_Ref
 * @version 0.2.14
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Toptour_Ref_Discovery_Provider {

	public static function provider_available( $provider ) {
		if ( 'manual' === $provider ) {
			return true;
		}

		if ( 'search_api' === $provider ) {
			return (bool) get_option( 'toptour_ref_search_api_configured', false );
		}

		return false;
	}

	public static function build_search_queries( $analysis ) {
		$destination = sanitize_text_field( $analysis['destination_candidate'] ?? '' );
		if ( '' === $destination ) {
			$destination = sanitize_text_field( $analysis['detected_destination'] ?? '' );
		}

		$stay_type = sanitize_text_field( $analysis['stay_type'] ?? '' );
		$source_languages = sanitize_text_field( $analysis['source_languages'] ?? '' );
		$expected_source_type = sanitize_text_field( $analysis['expected_source_type'] ?? 'mixed' );

		$areas = is_array( $analysis['finding_area_candidates'] ?? null ) ? $analysis['finding_area_candidates'] : [];
		$interests = is_array( $analysis['interest_candidates'] ?? null ) ? $analysis['interest_candidates'] : [];

		$area_words = [];
		$map = [
			'cleanliness' => 'cistota',
			'noise' => 'hluk',
			'food' => 'strava',
			'accessibility' => 'dostupnost',
			'transport' => 'doprava',
			'photos' => 'fotky hosti',
		];
		foreach ( $areas as $area ) {
			if ( isset( $map[ $area ] ) ) {
				$area_words[] = $map[ $area ];
			}
		}

		$base = trim( $destination . ' ' . ( $stay_type !== '' ? $stay_type : 'rodinny pobyt' ) );
		if ( '' === $base ) {
			$base = 'referencne zdroje ubytovanie';
		}

		$queries = [
			trim( $base . ' recenzie ' . implode( ' ', $area_words ) ),
			trim( $destination . ' ubytovanie rodiny s detmi recenzie' ),
			trim( $destination . ' hotel penzion fotky hosti' ),
			trim( $destination . ' Booking recenzie rodina deti' ),
			trim( $destination . ' TripAdvisor recenzie ubytovanie' ),
			trim( $destination . ' Google reviews ubytovanie rodina' ),
			trim( $destination . ' dostupnost ubytovanie rodinny pobyt' ),
		];

		if ( in_array( 'senior_support', $interests, true ) ) {
			$queries[] = trim( $destination . ' bezbarierove ubytovanie seniori recenzie' );
		}

		if ( 'video' === $expected_source_type ) {
			$queries[] = trim( $destination . ' ubytovanie video recenzie hostia' );
		}

		if ( '' !== $source_languages ) {
			$queries[] = trim( $destination . ' recenzie jazyk ' . $source_languages );
		}

		$queries = array_values( array_unique( array_filter( array_map( 'sanitize_text_field', $queries ) ) ) );
		return $queries;
	}

	public static function run_manual_discovery( $run_id ) {
		$run = Toptour_Ref_Discovery_Runs::get_run( $run_id );
		if ( ! $run ) {
			return [ 'success' => false, 'message' => 'Discovery run neexistuje.' ];
		}

		Toptour_Ref_Discovery_Runs::update_run_status( $run_id, 'completed' );
		return [
			'success' => true,
			'message' => 'Manual discovery pripraveny. Dotazy su ulozene na rucne pouzitie.',
		];
	}

	public static function run_search_api_discovery( $run_id ) {
		$run = Toptour_Ref_Discovery_Runs::get_run( $run_id );
		if ( ! $run ) {
			return [ 'success' => false, 'message' => 'Discovery run neexistuje.' ];
		}

		if ( ! self::provider_available( 'search_api' ) ) {
			Toptour_Ref_Discovery_Runs::create_missing_field(
				[
					'discovery_run_id'   => (int) $run->id,
					'collection_task_id' => (int) $run->collection_task_id,
					'field_key'          => 'discovery_provider_config',
					'field_label'        => 'Konfiguracia vyhladavacieho providera',
					'field_type'         => 'textarea',
					'field_status'       => 'missing',
					'is_required'        => 1,
					'help_text'          => 'Na automaticke web discovery je potrebne nastavit vyhladavaci provider/API.',
				]
			);

			Toptour_Ref_Discovery_Runs::sync_missing_fields_json( (int) $run->id );
			Toptour_Ref_Discovery_Runs::update_run_status( (int) $run->id, 'needs_input' );
			return [
				'success' => false,
				'message' => 'Search API provider nie je nakonfigurovany. Externe HTTP volania sa nespustili.',
			];
		}

		Toptour_Ref_Discovery_Runs::update_run_status( (int) $run->id, 'failed' );
		return [
			'success' => false,
			'message' => 'Search API provider je len placeholder. V MVP sa externe volania nevykonavaju.',
		];
	}
}
