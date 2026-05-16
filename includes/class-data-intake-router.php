<?php
/**
 * Data Intake Router.
 *
 * Manual URL intake for Collection Tasks.
 *
 * @package Toptour_Ref
 * @version 0.2.14
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Toptour_Ref_Data_Intake_Router {

	public static function process_manual_intake( $task_id, $payload = [] ) {
		$task_id = absint( $task_id );
		if ( $task_id <= 0 ) {
			return [
				'success' => false,
				'message' => 'Neplatná zberová úloha.',
				'details' => [],
			];
		}

		$task = Toptour_Ref_Collection_Tasks::get_task( $task_id );
		if ( ! $task ) {
			return [
				'success' => false,
				'message' => 'Zberová úloha neexistuje.',
				'details' => [],
			];
		}

		$source_url = trim( (string) ( $payload['source_url'] ?? '' ) );
		if ( '' === $source_url ) {
			return [
				'success' => false,
				'message' => 'URL zdroja je povinné.',
				'details' => [],
			];
		}
		if ( ! self::is_public_http_url( $source_url ) ) {
			return [
				'success' => false,
				'message' => 'Povolené sú iba verejné URL s protokolom HTTP alebo HTTPS.',
				'details' => [],
			];
		}

		$input_type = sanitize_key( $payload['input_type'] ?? 'auto' );
		$allowed_types = [ 'auto', 'ponuka', 'zariadenie', 'destinacia', 'referencia', 'fotodokaz', 'clanok', 'ine' ];
		if ( ! in_array( $input_type, $allowed_types, true ) ) {
			$input_type = 'auto';
		}

		$manager_note = sanitize_textarea_field( $payload['manager_note'] ?? '' );
		$destination_id = absint( $payload['destination_id'] ?? 0 );
		$facility_id = absint( $payload['facility_id'] ?? 0 );
		$offer_id = absint( $payload['offer_id'] ?? 0 );
		$normalized_url = self::normalize_url( $source_url );

		$details = [
			'source_created' => false,
			'source_updated' => false,
			'finding_created' => false,
			'offer_created' => false,
			'offer_updated' => false,
			'facility_candidate_created' => false,
			'destination_candidate_created' => false,
			'photo_evidence_created' => false,
			'run_id' => 0,
			'source_id' => 0,
			'finding_id' => 0,
			'offer_id' => $offer_id,
			'facility_id' => $facility_id,
			'destination_id' => $destination_id,
			'detected_type' => $input_type,
			'http_status' => 0,
		];

		$run_id = Toptour_Ref_Task_Runs::create_run(
			$task_id,
			[
				'status' => 'running',
				'started_at' => current_time( 'mysql' ),
				'summary' => 'Manual intake: ' . $normalized_url,
			]
		);
		$details['run_id'] = absint( $run_id );

		Toptour_Ref_Task_Events::log_event( $task_id, 'intake_started', null, [ 'url' => $normalized_url, 'run_id' => $run_id ], 'Intake started.' );

		$response = wp_remote_get(
			$source_url,
			[
				'timeout' => 12,
				'redirection' => 4,
				'headers' => [
					'Accept' => 'text/html,application/xhtml+xml;q=0.9,*/*;q=0.8',
				],
			]
		);

		if ( is_wp_error( $response ) ) {
			$message = sanitize_text_field( $response->get_error_message() );
			self::mark_run_failed( $run_id, $message );
			Toptour_Ref_Task_Events::log_event( $task_id, 'intake_fetch_failed', null, null, 'Fetch failed: ' . $message );
			return [
				'success' => false,
				'message' => 'Načítanie URL zlyhalo: ' . $message,
				'details' => $details,
			];
		}

		$http_status = absint( wp_remote_retrieve_response_code( $response ) );
		$details['http_status'] = $http_status;
		if ( $http_status < 200 || $http_status >= 400 ) {
			$message = 'HTTP status ' . $http_status;
			self::mark_run_failed( $run_id, $message );
			Toptour_Ref_Task_Events::log_event( $task_id, 'intake_fetch_failed', null, null, $message );
			return [
				'success' => false,
				'message' => 'Zdroj nie je dostupný (' . $message . ').',
				'details' => $details,
			];
		}

		$html = (string) wp_remote_retrieve_body( $response );
		$signals = self::extract_signals_from_html( $html, $source_url );
		$detected_type = ( 'auto' === $input_type ) ? self::infer_type( $normalized_url, $signals ) : $input_type;
		$details['detected_type'] = $detected_type;

		$source_result = self::upsert_source(
			$task_id,
			[
				'source_url' => $source_url,
				'normalized_url' => $normalized_url,
				'type' => $detected_type,
				'signals' => $signals,
				'note' => $manager_note,
				'facility_id' => $facility_id,
				'destination_id' => $destination_id,
			]
		);
		$source_id = absint( $source_result['source_id'] ?? 0 );
		$details['source_id'] = $source_id;
		$details['source_created'] = ! empty( $source_result['created'] );
		$details['source_updated'] = ! empty( $source_result['updated'] );
		if ( ! empty( $source_result['duplicate_detected'] ) ) {
			Toptour_Ref_Task_Events::log_event( $task_id, 'intake_duplicate_detected', null, [ 'source_id' => $source_id ], 'Source duplicate detected by normalized URL.' );
		}

		$resolved = self::resolve_candidates(
			[
				'type' => $detected_type,
				'title' => $signals['title'] ?? '',
				'note' => $manager_note,
				'facility_id' => $facility_id,
				'destination_id' => $destination_id,
			]
		);
		$details['facility_id'] = absint( $resolved['facility_id'] );
		$details['destination_id'] = absint( $resolved['destination_id'] );
		$details['facility_candidate_created'] = ! empty( $resolved['facility_created'] );
		$details['destination_candidate_created'] = ! empty( $resolved['destination_created'] );

		$offer_result = self::upsert_offer(
			[
				'type' => $detected_type,
				'signals' => $signals,
				'source_id' => $source_id,
				'offer_id' => $offer_id,
				'facility_id' => $details['facility_id'],
				'destination_id' => $details['destination_id'],
			]
		);
		$details['offer_created'] = ! empty( $offer_result['created'] );
		$details['offer_updated'] = ! empty( $offer_result['updated'] );
		if ( absint( $offer_result['offer_id'] ?? 0 ) > 0 ) {
			$details['offer_id'] = absint( $offer_result['offer_id'] );
		}

		$finding_id = self::create_finding(
			[
				'task_id' => $task_id,
				'run_id' => $run_id,
				'source_id' => $source_id,
				'type' => $detected_type,
				'signals' => $signals,
				'source_url' => $source_url,
				'normalized_url' => $normalized_url,
				'facility_id' => $details['facility_id'],
				'destination_id' => $details['destination_id'],
				'offer_id' => $details['offer_id'],
				'manager_note' => $manager_note,
			]
		);
		$details['finding_id'] = absint( $finding_id );
		$details['finding_created'] = absint( $finding_id ) > 0;

		$photo_created = self::create_photo_evidence(
			[
				'type' => $detected_type,
				'source_id' => $source_id,
				'finding_id' => $finding_id,
				'task_id' => $task_id,
				'offer_id' => $details['offer_id'],
				'facility_id' => $details['facility_id'],
				'destination_id' => $details['destination_id'],
				'image_urls' => $signals['image_urls'] ?? [],
			]
		);
		$details['photo_evidence_created'] = $photo_created;

		if ( absint( $details['offer_id'] ) > 0 ) {
			self::create_offer_snapshot( $details['offer_id'], $source_id, $signals, $http_status );
			Toptour_Ref_Task_Events::log_event( $task_id, 'intake_offer_routed', null, [ 'offer_id' => (int) $details['offer_id'] ], 'Offer route updated.' );
		}

		if ( $source_id > 0 ) {
			Toptour_Ref_Task_Events::log_event( $task_id, 'intake_source_ingested', null, [ 'source_id' => $source_id ], 'Source stored.' );
		}
		if ( $finding_id > 0 ) {
			Toptour_Ref_Task_Events::log_event( $task_id, 'intake_finding_created', null, [ 'finding_id' => $finding_id ], 'Finding created.' );
		}
		if ( $photo_created ) {
			Toptour_Ref_Task_Events::log_event( $task_id, 'intake_photo_evidence_created', null, null, 'Photo evidence links created.' );
		}

		Toptour_Ref_Task_Runs::update_run(
			$run_id,
			[
				'status' => 'finished',
				'finished_at' => current_time( 'mysql' ),
				'found_count' => 1,
				'new_count' => $finding_id ? 1 : 0,
				'duplicate_count' => ! empty( $source_result['duplicate_detected'] ) ? 1 : 0,
				'error_count' => 0,
				'summary' => 'Manual intake completed',
			]
		);

		Toptour_Ref_Task_Events::log_event( $task_id, 'intake_finished', null, [ 'detected_type' => $detected_type ], 'Intake finished.' );

		return [
			'success' => true,
			'message' => 'Reálny vstup dát bol spracovaný.',
			'details' => $details,
		];
	}

	private static function upsert_source( $task_id, $context ) {
		$existing = self::find_source_by_normalized_url( $context['normalized_url'] );
		$source_input = [
			'source_title' => sanitize_text_field( $context['signals']['title'] ?? '' ),
			'source_url' => esc_url_raw( $context['source_url'] ),
			'source_platform' => sanitize_text_field( wp_parse_url( $context['source_url'], PHP_URL_HOST ) ?: '' ),
			'source_type' => self::map_type_to_source_type( $context['type'] ),
			'source_origin' => 'manual_discovery',
			'target_type' => self::map_type_to_target_type( $context['type'], absint( $context['facility_id'] ), absint( $context['destination_id'] ) ),
			'target_id' => absint( $context['facility_id'] ) > 0 ? absint( $context['facility_id'] ) : absint( $context['destination_id'] ),
			'collection_task_id' => absint( $task_id ),
			'language' => sanitize_text_field( $context['signals']['language_hint'] ?? '' ),
			'captured_at' => current_time( 'mysql' ),
			'source_date' => '',
			'external_rating' => '',
			'external_review_count' => 0,
			'credibility_level' => 'unknown',
			'credibility_reason' => '',
			'credibility_updated_at' => '',
			'verification_method' => 'manual',
			'verification_notes' => '',
			'last_verified_at' => current_time( 'mysql' ),
			'suggested_credibility_level' => '',
			'suggestion_reason' => '',
			'suggestion_status' => 'manager_review',
			'suggestion_created_at' => '',
			'suggestion_resolved_at' => '',
			'suggestion_reviewed_by' => 0,
			'search_priority' => 'normal',
			'next_action' => 'review_source',
			'validation_status' => 'checked',
			'access_status' => 'accessible',
			'notes' => sanitize_textarea_field( $context['note'] ?? '' ),
		];
		$source_data = Toptour_Ref_Reference_Sources::sanitize_source_data( $source_input );
		$validation = Toptour_Ref_Reference_Sources::validate_source_data( $source_data );
		if ( true !== $validation ) {
			return [ 'source_id' => 0, 'created' => false, 'updated' => false, 'duplicate_detected' => false ];
		}

		if ( $existing ) {
			Toptour_Ref_Reference_Sources::update_source( (int) $existing->id, $source_data );
			return [ 'source_id' => (int) $existing->id, 'created' => false, 'updated' => true, 'duplicate_detected' => true ];
		}

		$new_id = Toptour_Ref_Reference_Sources::create_source( $source_data );
		return [ 'source_id' => (int) $new_id, 'created' => (bool) $new_id, 'updated' => false, 'duplicate_detected' => false ];
	}

	private static function resolve_candidates( $context ) {
		$result = [
			'facility_id' => absint( $context['facility_id'] ),
			'destination_id' => absint( $context['destination_id'] ),
			'facility_created' => false,
			'destination_created' => false,
		];

		if ( $result['facility_id'] <= 0 && in_array( $context['type'], [ 'ponuka', 'zariadenie' ], true ) ) {
			$name = self::extract_entity_name( $context['title'] );
			if ( '' !== $name ) {
				$facility_id = Toptour_Ref_Facilities::create_facility(
					[
						'name' => $name,
						'facility_type' => 'other',
						'country' => '',
						'region' => '',
						'city' => '',
						'address' => '',
						'website_url' => '',
						'official_source_url' => '',
						'status' => 'draft',
						'notes' => sanitize_textarea_field( $context['note'] ?? '' ),
					]
				);
				if ( $facility_id ) {
					$result['facility_id'] = (int) $facility_id;
					$result['facility_created'] = true;
				}
			}
		}

		if ( $result['destination_id'] <= 0 && 'destinacia' === $context['type'] ) {
			$name = self::extract_entity_name( $context['title'] );
			if ( '' !== $name ) {
				$destination_id = Toptour_Ref_Destinations::create_destination(
					[
						'name' => $name,
						'country' => '',
						'region' => '',
						'destination_type' => 'other',
						'seasonality' => '',
						'description' => '',
						'notes' => sanitize_textarea_field( $context['note'] ?? '' ),
						'status' => 'draft',
					]
				);
				if ( $destination_id ) {
					$result['destination_id'] = (int) $destination_id;
					$result['destination_created'] = true;
				}
			}
		}

		return $result;
	}

	private static function upsert_offer( $context ) {
		$result = [
			'offer_id' => absint( $context['offer_id'] ),
			'created' => false,
			'updated' => false,
		];

		if ( 'ponuka' !== $context['type'] ) {
			return $result;
		}

		$offer_name = sanitize_text_field( $context['signals']['title'] ?? '' );
		$offer_url = esc_url_raw( $context['signals']['canonical_url'] ?? '' );
		if ( '' === $offer_url ) {
			$offer_url = esc_url_raw( $context['signals']['source_url'] ?? '' );
		}
		$offer_data = [
			'facility_id' => absint( $context['facility_id'] ),
			'destination_id' => absint( $context['destination_id'] ),
			'reference_source_id' => absint( $context['source_id'] ),
			'offer_name' => $offer_name !== '' ? $offer_name : 'Ponuka z intake',
			'offer_url' => $offer_url,
			'offer_type' => 'general',
			'description_summary' => sanitize_textarea_field( $context['signals']['meta_description'] ?? '' ),
			'price_value' => null,
			'price_currency' => '',
			'price_note' => sanitize_text_field( $context['signals']['price_hint'] ?? '' ),
			'stay_duration' => '',
			'persons_min' => 0,
			'persons_max' => 0,
			'meal_plan' => '',
			'transport_type' => '',
			'accommodation_type' => '',
			'season' => '',
			'valid_from' => '',
			'valid_to' => '',
			'status' => 'needs_review',
			'created_by' => get_current_user_id(),
		];

		$offer_id = absint( $context['offer_id'] );
		if ( $offer_id <= 0 && '' !== $offer_url ) {
			$existing = Toptour_Ref_Offers::find_offer_by_url( $offer_url );
			if ( $existing ) {
				$offer_id = (int) $existing->id;
			}
		}

		if ( $offer_id > 0 ) {
			Toptour_Ref_Offers::update_offer( $offer_id, $offer_data );
			$result['offer_id'] = $offer_id;
			$result['updated'] = true;
			return $result;
		}

		$new_offer_id = Toptour_Ref_Offers::create_offer( $offer_data );
		if ( $new_offer_id ) {
			$result['offer_id'] = (int) $new_offer_id;
			$result['created'] = true;
		}

		return $result;
	}

	private static function create_finding( $context ) {
		$summary_parts = [
			'Zdroj: ' . esc_url_raw( $context['normalized_url'] ),
			'Typ vstupu: ' . sanitize_text_field( $context['type'] ),
		];
		if ( ! empty( $context['signals']['text_signal'] ) ) {
			$summary_parts[] = 'Signál: ' . sanitize_text_field( $context['signals']['text_signal'] );
		}
		if ( ! empty( $context['manager_note'] ) ) {
			$summary_parts[] = 'Poznámka manažéra: ' . sanitize_text_field( $context['manager_note'] );
		}

		$finding_input = [
			'finding_title' => sanitize_text_field( $context['signals']['title'] ?? 'Nález z intake URL' ),
			'task_id' => absint( $context['task_id'] ),
			'run_id' => absint( $context['run_id'] ),
			'source_url' => esc_url_raw( $context['source_url'] ),
			'source_title' => sanitize_text_field( $context['signals']['title'] ?? '' ),
			'source_type' => self::map_type_to_source_type( $context['type'] ),
			'excerpt' => sanitize_textarea_field( $context['signals']['meta_description'] ?? '' ),
			'detected_sentiment' => 'neutral',
			'review_published_at' => '',
			'analysis_performed_at' => current_time( 'mysql' ),
			'source_detected_at' => current_time( 'mysql' ),
			'source_last_checked_at' => current_time( 'mysql' ),
			'reference_language' => sanitize_text_field( $context['signals']['language_hint'] ?? '' ),
			'reference_type' => self::map_type_to_reference_type( $context['type'] ),
			'analysis_summary' => implode( ' | ', $summary_parts ),
			'analysis_status' => 'needs_review',
			'confidence_score' => null,
			'destination_mapping_note' => '',
			'poi_extraction_note' => '',
			'offer_relation_note' => '',
			'poi_candidate_id' => 0,
			'destination_id' => absint( $context['destination_id'] ),
			'supplier_id' => absint( $context['facility_id'] ),
			'offer_id' => absint( $context['offer_id'] ),
			'hash' => md5( $context['normalized_url'] . '|' . absint( $context['task_id'] ) . '|' . gmdate( 'YmdHi' ) ),
			'status' => 'pending_review',
			'found_at' => current_time( 'mysql' ),
			'reviewed_by' => 0,
			'reviewed_at' => '',
			'source_id' => absint( $context['source_id'] ),
			'signal_pattern_id' => 0,
			'target_type' => self::map_type_to_target_type( $context['type'], absint( $context['facility_id'] ), absint( $context['destination_id'] ) ),
			'target_id' => absint( $context['facility_id'] ) > 0 ? absint( $context['facility_id'] ) : absint( $context['destination_id'] ),
			'finding_type' => self::map_type_to_finding_type( $context['type'] ),
			'finding_area' => 'other',
			'signal_strength' => 'medium',
			'repetition_level' => 'single',
			'verification_status' => 'new',
			'evidence_type' => 'text',
			'evidence_excerpt' => sanitize_textarea_field( $context['signals']['text_signal'] ?? '' ),
			'evidence_url' => esc_url_raw( $context['source_url'] ),
			'observed_at' => current_time( 'mysql' ),
			'reviewer_name' => '',
			'reviewer_origin' => '',
			'language' => sanitize_text_field( $context['signals']['language_hint'] ?? '' ),
			'related_collection_task_id' => absint( $context['task_id'] ),
			'notes' => sanitize_textarea_field( $context['manager_note'] ),
		];

		$finding_data = Toptour_Ref_Findings::sanitize_finding_data( $finding_input );
		$validation = Toptour_Ref_Findings::validate_finding_data( $finding_data );
		if ( true !== $validation ) {
			return 0;
		}
		return (int) Toptour_Ref_Findings::create_finding( $finding_data );
	}

	private static function create_photo_evidence( $context ) {
		$image_urls = is_array( $context['image_urls'] ) ? $context['image_urls'] : [];
		if ( empty( $image_urls ) ) {
			return false;
		}
		if ( ! in_array( $context['type'], [ 'fotodokaz', 'ponuka', 'zariadenie', 'destinacia' ], true ) ) {
			return false;
		}

		$created = false;
		foreach ( array_slice( $image_urls, 0, 3 ) as $image_url ) {
			if ( ! self::is_public_http_url( $image_url ) ) {
				continue;
			}

			$photo_input = [
				'evidence_title' => 'Vizuálny dôkaz z intake',
				'source_id' => absint( $context['source_id'] ),
				'finding_id' => absint( $context['finding_id'] ),
				'target_type' => self::map_type_to_target_type( 'ponuka', absint( $context['facility_id'] ), absint( $context['destination_id'] ) ),
				'target_id' => absint( $context['offer_id'] ) > 0 ? absint( $context['offer_id'] ) : ( absint( $context['facility_id'] ) > 0 ? absint( $context['facility_id'] ) : absint( $context['destination_id'] ) ),
				'photo_type' => 'platform_photo',
				'comparison_category' => 'unknown',
				'visual_area' => 'other',
				'evidence_url' => esc_url_raw( $image_url ),
				'thumbnail_url' => '',
				'official_reference_url' => '',
				'guest_reference_url' => '',
				'observation_summary' => '',
				'visible_details' => '',
				'contradiction_note' => '',
				'verification_status' => 'new',
				'signal_strength' => 'medium',
				'observed_at' => current_time( 'mysql' ),
				'language' => '',
				'related_collection_task_id' => absint( $context['task_id'] ),
				'notes' => '',
			];

			$photo_data = Toptour_Ref_Photo_Evidence::sanitize_photo_evidence_data( $photo_input );
			$validation = Toptour_Ref_Photo_Evidence::validate_photo_evidence_data( $photo_data );
			if ( true !== $validation ) {
				continue;
			}

			$photo_id = Toptour_Ref_Photo_Evidence::create_photo_evidence( $photo_data );
			if ( $photo_id ) {
				$created = true;
			}
		}

		return $created;
	}

	private static function create_offer_snapshot( $offer_id, $source_id, $signals, $http_status ) {
		$status = ( $http_status >= 200 && $http_status < 400 ) ? 'new' : 'error';
		$snapshot_id = Toptour_Ref_Offer_Snapshots::create_snapshot(
			[
				'finding_id' => 0,
				'task_id' => 0,
				'run_id' => 0,
				'offer_id' => absint( $offer_id ),
				'supplier_id' => 0,
				'destination_id' => 0,
				'source_url' => esc_url_raw( $signals['canonical_url'] ?? '' ),
				'source_title' => sanitize_text_field( $signals['title'] ?? '' ),
				'offer_name' => sanitize_text_field( $signals['title'] ?? '' ),
				'offer_description_summary' => sanitize_textarea_field( $signals['meta_description'] ?? '' ),
				'price_value' => null,
				'price_currency' => '',
				'price_note' => sanitize_text_field( $signals['price_hint'] ?? '' ),
				'stay_duration' => '',
				'persons_min' => 0,
				'persons_max' => 0,
				'valid_from' => '',
				'valid_to' => '',
				'season' => '',
				'meal_plan' => '',
				'transport_type' => '',
				'accommodation_type' => '',
				'facility_category' => '',
				'included_services_summary' => '',
				'excluded_services_summary' => '',
				'availability_note' => absint( $source_id ) > 0 ? 'source_id=' . absint( $source_id ) : '',
				'booking_conditions_summary' => '',
				'public_offer_published_at' => '',
				'source_detected_at' => current_time( 'mysql' ),
				'source_last_checked_at' => current_time( 'mysql' ),
				'analysis_performed_at' => current_time( 'mysql' ),
				'snapshot_hash' => md5( absint( $offer_id ) . '|' . sanitize_text_field( $signals['title'] ?? '' ) . '|' . gmdate( 'YmdHi' ) ),
				'status' => $status,
			]
		);

		if ( $snapshot_id ) {
			Toptour_Ref_Offer_Snapshots::mark_previous_snapshots_superseded( 0, absint( $offer_id ), (int) $snapshot_id );
		}
	}

	private static function mark_run_failed( $run_id, $message ) {
		if ( absint( $run_id ) <= 0 ) {
			return;
		}
		Toptour_Ref_Task_Runs::update_run(
			$run_id,
			[
				'status' => 'failed',
				'finished_at' => current_time( 'mysql' ),
				'error_count' => 1,
				'summary' => sanitize_text_field( $message ),
			]
		);
	}

	private static function extract_signals_from_html( $html, $source_url ) {
		$signals = [
			'title' => '',
			'meta_description' => '',
			'language_hint' => '',
			'text_signal' => '',
			'image_urls' => [],
			'canonical_url' => esc_url_raw( $source_url ),
			'source_url' => esc_url_raw( $source_url ),
			'price_hint' => '',
		];

		if ( '' === trim( (string) $html ) || ! class_exists( 'DOMDocument' ) ) {
			return $signals;
		}

		libxml_use_internal_errors( true );
		$dom = new DOMDocument();
		$loaded = $dom->loadHTML( $html );
		libxml_clear_errors();
		if ( ! $loaded ) {
			return $signals;
		}

		$title_nodes = $dom->getElementsByTagName( 'title' );
		if ( $title_nodes->length > 0 ) {
			$signals['title'] = sanitize_text_field( trim( $title_nodes->item( 0 )->textContent ) );
		}

		$html_nodes = $dom->getElementsByTagName( 'html' );
		if ( $html_nodes->length > 0 ) {
			$signals['language_hint'] = sanitize_text_field( $html_nodes->item( 0 )->getAttribute( 'lang' ) );
		}

		$meta_nodes = $dom->getElementsByTagName( 'meta' );
		foreach ( $meta_nodes as $meta ) {
			$name = strtolower( trim( (string) $meta->getAttribute( 'name' ) ) );
			$property = strtolower( trim( (string) $meta->getAttribute( 'property' ) ) );
			$content = trim( (string) $meta->getAttribute( 'content' ) );
			if ( '' === $content ) {
				continue;
			}
			if ( '' === $signals['meta_description'] && in_array( $name, [ 'description', 'twitter:description' ], true ) ) {
				$signals['meta_description'] = sanitize_textarea_field( $content );
			}
			if ( '' === $signals['meta_description'] && 'og:description' === $property ) {
				$signals['meta_description'] = sanitize_textarea_field( $content );
			}
			if ( '' === $signals['canonical_url'] && 'og:url' === $property && self::is_public_http_url( $content ) ) {
				$signals['canonical_url'] = esc_url_raw( $content );
			}
			if ( '' === $signals['price_hint'] && ( 'product:price:amount' === $property || 'price' === $name ) ) {
				$signals['price_hint'] = sanitize_text_field( $content );
			}
			if ( count( $signals['image_urls'] ) < 3 && in_array( $property, [ 'og:image', 'twitter:image' ], true ) && self::is_public_http_url( $content ) ) {
				$signals['image_urls'][] = esc_url_raw( $content );
			}
		}

		$link_nodes = $dom->getElementsByTagName( 'link' );
		foreach ( $link_nodes as $link ) {
			$rel = strtolower( trim( (string) $link->getAttribute( 'rel' ) ) );
			if ( 'canonical' === $rel ) {
				$href = trim( (string) $link->getAttribute( 'href' ) );
				if ( self::is_public_http_url( $href ) ) {
					$signals['canonical_url'] = esc_url_raw( $href );
				}
			}
		}

		$img_nodes = $dom->getElementsByTagName( 'img' );
		foreach ( $img_nodes as $img ) {
			if ( count( $signals['image_urls'] ) >= 3 ) {
				break;
			}
			$src = trim( (string) $img->getAttribute( 'src' ) );
			$resolved = self::resolve_url( $src, $source_url );
			if ( self::is_public_http_url( $resolved ) ) {
				$signals['image_urls'][] = esc_url_raw( $resolved );
			}
		}

		$body_nodes = $dom->getElementsByTagName( 'body' );
		if ( $body_nodes->length > 0 ) {
			$text = wp_strip_all_tags( $body_nodes->item( 0 )->textContent );
			$text = preg_replace( '/\s+/', ' ', $text );
			$text = trim( (string) $text );
			if ( '' !== $text ) {
				$signals['text_signal'] = function_exists( 'mb_substr' )
					? sanitize_text_field( mb_substr( $text, 0, 320 ) )
					: sanitize_text_field( substr( $text, 0, 320 ) );
			}
		}

		$signals['image_urls'] = array_values( array_unique( array_filter( $signals['image_urls'] ) ) );
		return $signals;
	}

	private static function infer_type( $normalized_url, $signals ) {
		$haystack = strtolower( implode( ' ', [
			(string) $normalized_url,
			(string) ( $signals['title'] ?? '' ),
			(string) ( $signals['meta_description'] ?? '' ),
			(string) ( $signals['text_signal'] ?? '' ),
		] ) );

		if ( preg_match( '/offer|deal|discount|special|book now|rezerv|ponuk|cena|price|eur|package/i', $haystack ) ) {
			return 'ponuka';
		}
		if ( preg_match( '/hotel|resort|apartment|pension|ubytovanie|zariadenie/i', $haystack ) ) {
			return 'zariadenie';
		}
		if ( preg_match( '/destination|destin|region|city|mountain|beach|miesto|lokalita/i', $haystack ) ) {
			return 'destinacia';
		}
		if ( preg_match( '/photo|gallery|image|obrazok|fotk/i', $haystack ) ) {
			return 'fotodokaz';
		}
		if ( preg_match( '/blog|article|guide|clanok|sprievodca/i', $haystack ) ) {
			return 'clanok';
		}
		if ( preg_match( '/review|rating|recenz|feedback|experience/i', $haystack ) ) {
			return 'referencia';
		}
		return 'ine';
	}

	private static function find_source_by_normalized_url( $normalized_url ) {
		global $wpdb;
		$table = Toptour_Ref_Reference_Sources::get_table_name();
		$host = wp_parse_url( $normalized_url, PHP_URL_HOST );
		if ( ! $host ) {
			return null;
		}
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, source_url FROM $table WHERE source_url LIKE %s ORDER BY id DESC LIMIT 300",
				'%' . $wpdb->esc_like( $host ) . '%'
			)
		);
		foreach ( (array) $rows as $row ) {
			if ( self::normalize_url( (string) $row->source_url ) === $normalized_url ) {
				return (object) [ 'id' => absint( $row->id ) ];
			}
		}
		return null;
	}

	private static function extract_entity_name( $title ) {
		$title = sanitize_text_field( (string) $title );
		if ( '' === $title ) {
			return '';
		}
		$parts = preg_split( '/\s[\-\|]\s/', $title );
		$first = trim( (string) ( $parts[0] ?? $title ) );
		if ( strlen( $first ) < 3 ) {
			return '';
		}
		return function_exists( 'mb_substr' ) ? mb_substr( $first, 0, 120 ) : substr( $first, 0, 120 );
	}

	private static function map_type_to_source_type( $type ) {
		switch ( $type ) {
			case 'referencia':
				return 'review';
			case 'fotodokaz':
				return 'guest_photo';
			case 'clanok':
				return 'article';
			case 'ponuka':
				return 'mixed';
			default:
				return 'other';
		}
	}

	private static function map_type_to_target_type( $type, $facility_id, $destination_id ) {
		if ( $facility_id > 0 ) {
			return 'facility';
		}
		if ( $destination_id > 0 ) {
			return 'destination';
		}
		if ( 'ponuka' === $type ) {
			return 'offer';
		}
		if ( 'destinacia' === $type ) {
			return 'destination';
		}
		if ( 'zariadenie' === $type ) {
			return 'facility';
		}
		return 'general';
	}

	private static function map_type_to_reference_type( $type ) {
		switch ( $type ) {
			case 'referencia':
				return 'guest_review';
			case 'clanok':
				return 'article_mention';
			case 'fotodokaz':
				return 'social_mention';
			default:
				return 'other';
		}
	}

	private static function map_type_to_finding_type( $type ) {
		switch ( $type ) {
			case 'ponuka':
				return 'positive';
			case 'referencia':
				return 'source_quality';
			default:
				return 'neutral';
		}
	}

	private static function normalize_url( $url ) {
		$parts = wp_parse_url( trim( (string) $url ) );
		if ( ! is_array( $parts ) || empty( $parts['host'] ) ) {
			return trim( (string) $url );
		}
		$scheme = isset( $parts['scheme'] ) ? strtolower( $parts['scheme'] ) : 'https';
		$host = strtolower( $parts['host'] );
		$path = isset( $parts['path'] ) ? rtrim( (string) $parts['path'], '/' ) : '';
		$query = '';
		if ( ! empty( $parts['query'] ) ) {
			parse_str( $parts['query'], $query_args );
			if ( is_array( $query_args ) ) {
				foreach ( array_keys( $query_args ) as $key ) {
					if ( strpos( (string) $key, 'utm_' ) === 0 ) {
						unset( $query_args[ $key ] );
					}
				}
				ksort( $query_args );
				$query = http_build_query( $query_args );
			}
		}
		return $scheme . '://' . $host . $path . ( $query ? '?' . $query : '' );
	}

	private static function is_public_http_url( $url ) {
		$url = trim( (string) $url );
		if ( '' === $url || ! wp_http_validate_url( $url ) ) {
			return false;
		}
		$scheme = strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) );
		if ( ! in_array( $scheme, [ 'http', 'https' ], true ) ) {
			return false;
		}
		$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		if ( '' === $host || in_array( $host, [ 'localhost', '127.0.0.1', '::1' ], true ) ) {
			return false;
		}
		if ( preg_match( '/^10\.|^172\.(1[6-9]|2\d|3[01])\.|^192\.168\./', $host ) ) {
			return false;
		}
		return true;
	}

	private static function resolve_url( $url, $base_url ) {
		$url = trim( (string) $url );
		if ( '' === $url ) {
			return '';
		}
		if ( self::is_public_http_url( $url ) ) {
			return $url;
		}
		if ( strpos( $url, '//' ) === 0 ) {
			$scheme = wp_parse_url( $base_url, PHP_URL_SCHEME );
			return $scheme . ':' . $url;
		}
		if ( strpos( $url, '/' ) === 0 ) {
			$scheme = wp_parse_url( $base_url, PHP_URL_SCHEME );
			$host = wp_parse_url( $base_url, PHP_URL_HOST );
			if ( $scheme && $host ) {
				return $scheme . '://' . $host . $url;
			}
		}
		return '';
	}
}
