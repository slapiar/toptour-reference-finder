<?php
/**
 * AI Outbox Importer class.
 *
 * Maps AI bridge outbox JSON payloads into internal plugin modules.
 *
 * @package Toptour_Ref
 * @version 0.2.14
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Toptour_Ref_AI_Outbox_Importer {

	const REPORT_OPTION_KEY = 'toptour_ref_ai_import_reports';
	const REPORT_MAX_DEFAULT = 500;
	const LOCK_OPTION_KEY = 'toptour_ref_ai_outbox_import_lock';

	public static function get_import_reports( $limit = 50 ) {
		$rows = get_option( self::REPORT_OPTION_KEY, [] );
		if ( ! is_array( $rows ) ) {
			$rows = [];
		}

		$normalized = [];
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$normalized[] = self::normalize_report_row( $row );
		}

		$limit = max( 1, absint( $limit ) );
		return array_slice( $normalized, 0, $limit );
	}

	public static function clear_import_reports( $older_than_days = 0 ) {
		$rows = get_option( self::REPORT_OPTION_KEY, [] );
		if ( ! is_array( $rows ) ) {
			$rows = [];
		}

		$before = count( $rows );
		$days = absint( $older_than_days );
		if ( $days <= 0 ) {
			update_option( self::REPORT_OPTION_KEY, [] );
			return [
				'removed' => $before,
				'remaining' => 0,
			];
		}

		$cutoff = current_time( 'timestamp' ) - ( $days * DAY_IN_SECONDS );
		$filtered = [];
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$created_ts = strtotime( (string) ( $row['created_at'] ?? '' ) );
			if ( false === $created_ts || $created_ts >= $cutoff ) {
				$filtered[] = $row;
			}
		}

		update_option( self::REPORT_OPTION_KEY, array_values( $filtered ) );
		return [
			'removed' => max( 0, $before - count( $filtered ) ),
			'remaining' => count( $filtered ),
		];
	}

	public static function get_report_total_count() {
		$rows = get_option( self::REPORT_OPTION_KEY, [] );
		return is_array( $rows ) ? count( $rows ) : 0;
	}

	public static function process_pending_outbox( $limit = null ) {
		$lock_token = self::acquire_lock();
		if ( '' === $lock_token ) {
			return [
				'success' => false,
				'message' => 'AI outbox import already running.',
				'processed' => 0,
				'failed' => 0,
				'imported' => 0,
			];
		}

		try {
		Toptour_Ref_AI_Bridge::ensure_directories();
		$paths = Toptour_Ref_AI_Bridge::get_paths();
		$batch_limit = null === $limit ? 20 : max( 1, absint( $limit ) );

		$outbox_files = glob( trailingslashit( $paths['outbox_dir'] ) . '*.out.json' );
		if ( ! is_array( $outbox_files ) || empty( $outbox_files ) ) {
			return [
				'success' => true,
				'message' => 'Outbox je prazdny.',
				'processed' => 0,
				'failed' => 0,
				'imported' => 0,
			];
		}

		sort( $outbox_files );
		$processed = 0;
		$failed = 0;
		$imported = 0;

		foreach ( array_slice( $outbox_files, 0, $batch_limit ) as $outbox_file ) {
			$claim = self::claim_file_for_processing( $outbox_file );
			if ( empty( $claim['claimed_path'] ) ) {
				continue;
			}

			$result = self::process_outbox_file( $claim['claimed_path'], $claim['original_name'] );
			$processed++;
			if ( ! empty( $result['success'] ) ) {
				$imported++;
			} else {
				$failed++;
			}
		}

		return [
			'success' => true,
			'message' => sprintf( 'Outbox import: %d uspesne, %d chybne.', $imported, $failed ),
			'processed' => $processed,
			'failed' => $failed,
			'imported' => $imported,
		];
		} finally {
			self::release_lock( $lock_token );
		}
	}

	private static function process_outbox_file( $outbox_file, $original_name ) {
		$outbox_name = sanitize_file_name( (string) $original_name );
		$raw = file_get_contents( $outbox_file );
		if ( false === $raw || '' === trim( $raw ) ) {
			self::append_import_report(
				[
					'success' => false,
					'outbox_file' => $outbox_name,
					'task_id' => 0,
					'run_id' => 0,
					'message' => 'Outbox file is empty.',
				]
			);
			self::move_to_error( $outbox_file, 'outbox_empty', $outbox_name );
			return [ 'success' => false ];
		}

		$payload = json_decode( $raw, true );
		if ( ! is_array( $payload ) ) {
			self::append_import_report(
				[
					'success' => false,
					'outbox_file' => $outbox_name,
					'task_id' => 0,
					'run_id' => 0,
					'message' => 'Outbox payload is not valid JSON.',
				]
			);
			self::move_to_error( $outbox_file, 'outbox_invalid_json', $outbox_name );
			return [ 'success' => false ];
		}

		$batch_id = sanitize_text_field( (string) ( $payload['input']['batch_id'] ?? '' ) );
		if ( '' === $batch_id ) {
			$batch_id = 'auto-' . md5( $outbox_name . '|' . wp_json_encode( $payload['input'] ?? [] ) );
		}

		$status = sanitize_text_field( (string) ( $payload['status'] ?? '' ) );
		if ( 'error' === $status ) {
			self::append_import_report(
				[
					'success' => false,
					'outbox_file' => $outbox_name,
					'task_id' => absint( $payload['input']['task_id'] ?? 0 ),
					'run_id' => 0,
					'message' => 'Outbox payload has status=error.',
				]
			);
			Toptour_Ref_AI_Batch_Registry::mark_failed( $batch_id, 'outbox_status_error' );
			self::move_to_error( $outbox_file, 'outbox_status_error', $outbox_name );
			return [ 'success' => false ];
		}

		$task_id = absint( $payload['input']['task_id'] ?? 0 );
		if ( $task_id <= 0 || ! Toptour_Ref_Collection_Tasks::get_task( $task_id ) ) {
			self::append_import_report(
				[
					'success' => false,
					'outbox_file' => $outbox_name,
					'task_id' => $task_id,
					'run_id' => 0,
					'message' => 'Outbox payload references invalid task.',
				]
			);
			Toptour_Ref_AI_Batch_Registry::mark_failed( $batch_id, 'outbox_invalid_task' );
			self::move_to_error( $outbox_file, 'outbox_invalid_task', $outbox_name );
			return [ 'success' => false ];
		}

		$batch_claim = Toptour_Ref_AI_Batch_Registry::claim_batch(
			$batch_id,
			$task_id,
			$outbox_name,
			'importer:' . gethostname()
		);

		if ( empty( $batch_claim['ok'] ) ) {
			$state = sanitize_text_field( (string) ( $batch_claim['state'] ?? 'batch_claim_failed' ) );
			self::append_import_report(
				[
					'success' => false,
					'outbox_file' => $outbox_name,
					'task_id' => $task_id,
					'run_id' => 0,
					'message' => 'Batch skipped: ' . $state,
				]
			);
			if ( 'already_processed' === $state ) {
				self::move_to_archive( $outbox_file, $outbox_name );
				return [ 'success' => true, 'skipped' => true ];
			}
			self::move_to_error( $outbox_file, 'outbox_batch_claim_failed', $outbox_name );
			return [ 'success' => false ];
		}

		$structured = is_array( $payload['structured_output'] ?? null ) ? $payload['structured_output'] : [];
		if ( empty( $structured ) ) {
			self::append_import_report(
				[
					'success' => false,
					'outbox_file' => $outbox_name,
					'task_id' => $task_id,
					'run_id' => 0,
					'message' => 'Outbox payload missing structured_output.',
				]
			);
			Toptour_Ref_AI_Batch_Registry::mark_failed( $batch_id, 'outbox_missing_structured_output' );
			self::move_to_error( $outbox_file, 'outbox_missing_structured_output', $outbox_name );
			return [ 'success' => false ];
		}

		$run_id = Toptour_Ref_Task_Runs::create_run(
			$task_id,
			[
				'status' => 'running',
				'started_at' => current_time( 'mysql' ),
				'summary' => 'AI outbox import: ' . sanitize_file_name( basename( $outbox_file ) ),
			]
		);

		Toptour_Ref_Task_Events::log_event( $task_id, 'run_started', null, [ 'run_id' => absint( $run_id ) ], 'AI outbox import started.' );

		$import_result = self::import_structured_output( $task_id, absint( $run_id ), $payload, $structured );
		if ( empty( $import_result['success'] ) ) {
			Toptour_Ref_Task_Runs::update_run(
				$run_id,
				[
					'status' => 'failed',
					'finished_at' => current_time( 'mysql' ),
					'error_count' => max( 1, absint( $import_result['error_count'] ?? 1 ) ),
					'summary' => sanitize_text_field( (string) ( $import_result['message'] ?? 'AI outbox import failed' ) ),
				]
			);
			Toptour_Ref_Task_Events::log_event( $task_id, 'error', null, null, sanitize_text_field( (string) ( $import_result['message'] ?? 'AI outbox import failed' ) ) );
			self::append_import_report(
				[
					'success' => false,
					'outbox_file' => $outbox_name,
					'task_id' => $task_id,
					'run_id' => absint( $run_id ),
					'message' => sanitize_text_field( (string) ( $import_result['message'] ?? 'AI outbox import failed' ) ),
					'metrics' => [
						'found_count' => absint( $import_result['found_count'] ?? 0 ),
						'new_count' => absint( $import_result['new_count'] ?? 0 ),
						'duplicate_count' => absint( $import_result['duplicate_count'] ?? 0 ),
						'error_count' => absint( $import_result['error_count'] ?? 1 ),
					],
					'module_metrics' => is_array( $import_result['module_metrics'] ?? null ) ? $import_result['module_metrics'] : [],
				]
			);
			Toptour_Ref_AI_Batch_Registry::mark_failed( $batch_id, (string) ( $import_result['message'] ?? 'outbox_import_failed' ) );
			self::move_to_error( $outbox_file, 'outbox_import_failed', $outbox_name );
			return [ 'success' => false ];
		}

		Toptour_Ref_Task_Runs::update_run(
			$run_id,
			[
				'status' => 'finished',
				'finished_at' => current_time( 'mysql' ),
				'found_count' => absint( $import_result['found_count'] ?? 0 ),
				'new_count' => absint( $import_result['new_count'] ?? 0 ),
				'duplicate_count' => absint( $import_result['duplicate_count'] ?? 0 ),
				'error_count' => absint( $import_result['error_count'] ?? 0 ),
				'summary' => sanitize_text_field( (string) ( $import_result['message'] ?? 'AI outbox import finished' ) ),
			]
		);

		Toptour_Ref_Task_Events::log_event( $task_id, 'run_finished', null, [ 'run_id' => absint( $run_id ) ], 'AI outbox import finished.' );
		self::append_import_report(
			[
				'success' => true,
				'outbox_file' => $outbox_name,
				'task_id' => $task_id,
				'run_id' => absint( $run_id ),
				'message' => sanitize_text_field( (string) ( $import_result['message'] ?? 'AI outbox import finished' ) ),
				'metrics' => [
					'found_count' => absint( $import_result['found_count'] ?? 0 ),
					'new_count' => absint( $import_result['new_count'] ?? 0 ),
					'duplicate_count' => absint( $import_result['duplicate_count'] ?? 0 ),
					'error_count' => absint( $import_result['error_count'] ?? 0 ),
				],
				'module_metrics' => is_array( $import_result['module_metrics'] ?? null ) ? $import_result['module_metrics'] : [],
			]
		);
		Toptour_Ref_AI_Batch_Registry::mark_processed( $batch_id );
		self::move_to_archive( $outbox_file, $outbox_name );

		return [
			'success' => true,
			'run_id' => absint( $run_id ),
		];
	}

	private static function import_structured_output( $task_id, $run_id, $payload, $structured ) {
		$source_result = self::import_candidate_sources( $task_id, $structured['candidate_sources'] ?? [] );
		$facility_result = self::import_candidate_facilities( $structured['candidate_facilities'] ?? [] );
		$finding_result = self::import_pending_findings( $task_id, $run_id, $structured['pending_findings'] ?? [], $source_result['source_map'] ?? [] );
		$photo_result = self::import_photo_candidates( $task_id, $structured['photo_evidence_candidates'] ?? [], $source_result['source_map'] ?? [], $finding_result['finding_map'] ?? [] );

		$error_count = absint( $source_result['errors'] ?? 0 ) + absint( $facility_result['errors'] ?? 0 ) + absint( $finding_result['errors'] ?? 0 ) + absint( $photo_result['errors'] ?? 0 );
		$new_count = absint( $source_result['created'] ?? 0 ) + absint( $facility_result['created'] ?? 0 ) + absint( $finding_result['created'] ?? 0 ) + absint( $photo_result['created'] ?? 0 );
		$duplicate_count = absint( $source_result['updated'] ?? 0 ) + absint( $facility_result['updated'] ?? 0 ) + absint( $finding_result['updated'] ?? 0 ) + absint( $photo_result['updated'] ?? 0 );
		$found_count = count( (array) ( $structured['candidate_sources'] ?? [] ) ) + count( (array) ( $structured['candidate_facilities'] ?? [] ) ) + count( (array) ( $structured['pending_findings'] ?? [] ) ) + count( (array) ( $structured['photo_evidence_candidates'] ?? [] ) );

		if ( $new_count <= 0 && $duplicate_count <= 0 && $error_count > 0 ) {
			return [
				'success' => false,
				'message' => 'AI outbox import failed: no records imported.',
				'found_count' => $found_count,
				'new_count' => 0,
				'duplicate_count' => 0,
				'error_count' => $error_count,
			];
		}

		$message = sprintf(
			'AI import created=%d updated=%d errors=%d',
			$new_count,
			$duplicate_count,
			$error_count
		);

		return [
			'success' => true,
			'message' => $message,
			'found_count' => $found_count,
			'new_count' => $new_count,
			'duplicate_count' => $duplicate_count,
			'error_count' => $error_count,
			'module_metrics' => [
				'sources' => [
					'created' => absint( $source_result['created'] ?? 0 ),
					'updated' => absint( $source_result['updated'] ?? 0 ),
					'errors' => absint( $source_result['errors'] ?? 0 ),
				],
				'facilities' => [
					'created' => absint( $facility_result['created'] ?? 0 ),
					'updated' => absint( $facility_result['updated'] ?? 0 ),
					'errors' => absint( $facility_result['errors'] ?? 0 ),
				],
				'findings' => [
					'created' => absint( $finding_result['created'] ?? 0 ),
					'updated' => absint( $finding_result['updated'] ?? 0 ),
					'errors' => absint( $finding_result['errors'] ?? 0 ),
				],
				'photo_evidence' => [
					'created' => absint( $photo_result['created'] ?? 0 ),
					'updated' => absint( $photo_result['updated'] ?? 0 ),
					'errors' => absint( $photo_result['errors'] ?? 0 ),
				],
			],
		];
	}

	private static function append_import_report( $row ) {
		$rows = get_option( self::REPORT_OPTION_KEY, [] );
		if ( ! is_array( $rows ) ) {
			$rows = [];
		}

		$entry = self::normalize_report_row( is_array( $row ) ? $row : [] );
		array_unshift( $rows, $entry );

		$max_keep = absint( apply_filters( 'toptour_ref_ai_import_report_max_entries', self::REPORT_MAX_DEFAULT ) );
		$max_keep = max( 50, min( 5000, $max_keep ) );
		if ( count( $rows ) > $max_keep ) {
			$rows = array_slice( $rows, 0, $max_keep );
		}

		update_option( self::REPORT_OPTION_KEY, array_values( $rows ) );
	}

	private static function normalize_report_row( $row ) {
		$metrics = is_array( $row['metrics'] ?? null ) ? $row['metrics'] : [];
		$module_metrics = is_array( $row['module_metrics'] ?? null ) ? $row['module_metrics'] : [];

		$normalized_modules = [];
		foreach ( [ 'sources', 'facilities', 'findings', 'photo_evidence' ] as $module_key ) {
			$module_row = is_array( $module_metrics[ $module_key ] ?? null ) ? $module_metrics[ $module_key ] : [];
			$normalized_modules[ $module_key ] = [
				'created' => absint( $module_row['created'] ?? 0 ),
				'updated' => absint( $module_row['updated'] ?? 0 ),
				'errors' => absint( $module_row['errors'] ?? 0 ),
			];
		}

		return [
			'created_at' => sanitize_text_field( (string) ( $row['created_at'] ?? current_time( 'mysql' ) ) ),
			'success' => ! empty( $row['success'] ) ? 1 : 0,
			'outbox_file' => sanitize_file_name( (string) ( $row['outbox_file'] ?? '' ) ),
			'task_id' => absint( $row['task_id'] ?? 0 ),
			'run_id' => absint( $row['run_id'] ?? 0 ),
			'message' => sanitize_text_field( (string) ( $row['message'] ?? '' ) ),
			'metrics' => [
				'found_count' => absint( $metrics['found_count'] ?? 0 ),
				'new_count' => absint( $metrics['new_count'] ?? 0 ),
				'duplicate_count' => absint( $metrics['duplicate_count'] ?? 0 ),
				'error_count' => absint( $metrics['error_count'] ?? 0 ),
			],
			'module_metrics' => $normalized_modules,
		];
	}

	private static function import_candidate_sources( $task_id, $rows ) {
		if ( ! is_array( $rows ) ) {
			$rows = [];
		}

		$result = [
			'created' => 0,
			'updated' => 0,
			'errors' => 0,
			'source_map' => [],
		];

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				$result['errors']++;
				continue;
			}

			$url = esc_url_raw( (string) ( $row['url'] ?? '' ) );
			$title = sanitize_text_field( (string) ( $row['title'] ?? '' ) );
			if ( '' === $title ) {
				$title = self::fallback_title_from_url( $url );
			}
			if ( '' === $title ) {
				$result['errors']++;
				continue;
			}

			$facility_id = absint( $row['facility_id'] ?? 0 );
			$destination_id = absint( $row['destination_id'] ?? 0 );
			$target_type = $facility_id > 0 ? 'facility' : ( $destination_id > 0 ? 'destination' : 'general' );
			$target_id = $facility_id > 0 ? $facility_id : $destination_id;
			$status_note = sanitize_text_field( (string) ( $row['status'] ?? 'candidate' ) );

			$input = [
				'source_title' => $title,
				'source_url' => $url,
				'source_platform' => sanitize_text_field( (string) ( $row['platform'] ?? self::platform_from_url( $url ) ) ),
				'source_type' => 'review',
				'source_origin' => 'manual_discovery',
				'target_type' => $target_type,
				'target_id' => $target_id,
				'collection_task_id' => absint( $task_id ),
				'language' => '',
				'captured_at' => current_time( 'mysql' ),
				'source_date' => '',
				'external_rating' => '',
				'external_review_count' => 0,
				'credibility_level' => 'unknown',
				'credibility_reason' => '',
				'credibility_updated_at' => '',
				'verification_method' => 'manual',
				'verification_notes' => '',
				'last_verified_at' => '',
				'suggested_credibility_level' => '',
				'suggestion_reason' => '',
				'suggestion_status' => 'manager_review',
				'suggestion_created_at' => '',
				'suggestion_resolved_at' => '',
				'suggestion_reviewed_by' => 0,
				'search_priority' => 'normal',
				'next_action' => 'review_source',
				'validation_status' => 'new',
				'access_status' => 'unknown',
				'notes' => self::append_note( (string) ( $row['notes'] ?? '' ), 'AI import status=' . $status_note ),
			];

			$data = Toptour_Ref_Reference_Sources::sanitize_source_data( $input );
			$validation = Toptour_Ref_Reference_Sources::validate_source_data( $data );
			if ( true !== $validation ) {
				$result['errors']++;
				continue;
			}

			$existing_id = self::find_existing_source_id( $url );
			if ( $existing_id > 0 ) {
				$ok = Toptour_Ref_Reference_Sources::update_source( $existing_id, $data );
				if ( $ok ) {
					$result['updated']++;
				} else {
					$result['errors']++;
					continue;
				}
				$new_source_id = $existing_id;
			} else {
				$new_source_id = (int) Toptour_Ref_Reference_Sources::create_source( $data );
				if ( $new_source_id > 0 ) {
					$result['created']++;
				} else {
					$result['errors']++;
					continue;
				}
			}

			$legacy_source_id = absint( $row['source_id'] ?? 0 );
			if ( $legacy_source_id > 0 ) {
				$result['source_map'][ $legacy_source_id ] = $new_source_id;
			}
		}

		return $result;
	}

	private static function import_candidate_facilities( $rows ) {
		if ( ! is_array( $rows ) ) {
			$rows = [];
		}

		$result = [
			'created' => 0,
			'updated' => 0,
			'errors' => 0,
		];

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				$result['errors']++;
				continue;
			}

			$facility_id = absint( $row['facility_id'] ?? 0 );
			$name = sanitize_text_field( (string) ( $row['name'] ?? '' ) );
			if ( $facility_id <= 0 && '' === $name ) {
				$result['errors']++;
				continue;
			}

			$input = [
				'name' => $name,
				'slug' => '',
				'facility_type' => 'other',
				'country' => '',
				'region' => '',
				'city' => '',
				'address' => '',
				'website_url' => '',
				'official_source_url' => '',
				'status' => 'draft',
				'notes' => self::append_note( (string) ( $row['notes'] ?? '' ), 'AI facility candidate: ' . sanitize_text_field( (string) ( $row['status'] ?? 'requires_review' ) ) ),
			];

			$data = Toptour_Ref_Facilities::sanitize_facility_data( $input );
			$validation = Toptour_Ref_Facilities::validate_facility_data( $data );
			if ( true !== $validation ) {
				$result['errors']++;
				continue;
			}

			if ( $facility_id > 0 && Toptour_Ref_Facilities::get_facility( $facility_id ) ) {
				$ok = Toptour_Ref_Facilities::update_facility( $facility_id, $data );
				if ( $ok ) {
					$result['updated']++;
				} else {
					$result['errors']++;
				}
				continue;
			}

			$new_id = Toptour_Ref_Facilities::create_facility( $data );
			if ( $new_id ) {
				$result['created']++;
			} else {
				$result['errors']++;
			}
		}

		return $result;
	}

	private static function import_pending_findings( $task_id, $run_id, $rows, $source_map ) {
		if ( ! is_array( $rows ) ) {
			$rows = [];
		}
		if ( ! is_array( $source_map ) ) {
			$source_map = [];
		}

		$result = [
			'created' => 0,
			'updated' => 0,
			'errors' => 0,
			'finding_map' => [],
		];

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				$result['errors']++;
				continue;
			}

			$legacy_source_id = absint( $row['source_id'] ?? 0 );
			$source_id = $legacy_source_id > 0 && isset( $source_map[ $legacy_source_id ] ) ? absint( $source_map[ $legacy_source_id ] ) : absint( $legacy_source_id );
			$source = $source_id > 0 ? Toptour_Ref_Reference_Sources::get_source( $source_id ) : null;

			$facility_id = absint( $row['facility_id'] ?? 0 );
			$destination_id = absint( $row['destination_id'] ?? 0 );
			$target_type = $facility_id > 0 ? 'facility' : ( $destination_id > 0 ? 'destination' : 'general' );
			$target_id = $facility_id > 0 ? $facility_id : $destination_id;

			$summary = sanitize_textarea_field( (string) ( $row['summary'] ?? '' ) );
			if ( '' === $summary ) {
				$result['errors']++;
				continue;
			}

			$finding_area = sanitize_key( (string) ( $row['category'] ?? '' ) );
			if ( '' !== $finding_area && ! in_array( $finding_area, Toptour_Ref_Findings::get_allowed_finding_areas(), true ) ) {
				$finding_area = 'other';
			}

			$source_url = $source && ! empty( $source->source_url ) ? (string) $source->source_url : '';
			$dedupe_hash = self::build_finding_dedupe_hash( $task_id, $source_url, $summary, $target_type, $target_id );
			$existing_finding_id = self::find_existing_finding_id_by_hash( $dedupe_hash );
			if ( $existing_finding_id <= 0 ) {
				$existing_finding_id = self::find_existing_finding_id_by_signature( $task_id, $source_url, $summary, $target_type, $target_id );
			}
			if ( $existing_finding_id > 0 ) {
				$result['updated']++;
				if ( $legacy_source_id > 0 ) {
					$result['finding_map'][ $legacy_source_id ] = $existing_finding_id;
				}
				continue;
			}

			$input = [
				'finding_title' => self::trim_text( $summary, 140 ),
				'task_id' => absint( $task_id ),
				'run_id' => absint( $run_id ),
				'source_url' => $source_url,
				'source_title' => $source && ! empty( $source->source_title ) ? (string) $source->source_title : '',
				'source_type' => $source && ! empty( $source->source_type ) ? (string) $source->source_type : 'other',
				'excerpt' => $summary,
				'detected_sentiment' => 'neutral',
				'review_published_at' => '',
				'analysis_performed_at' => current_time( 'mysql' ),
				'source_detected_at' => current_time( 'mysql' ),
				'source_last_checked_at' => current_time( 'mysql' ),
				'reference_language' => '',
				'reference_type' => 'other',
				'analysis_summary' => self::append_note( $summary, 'AI pending_review import' ),
				'analysis_status' => 'needs_review',
				'confidence_score' => null,
				'destination_mapping_note' => '',
				'poi_extraction_note' => '',
				'offer_relation_note' => '',
				'poi_candidate_id' => 0,
				'destination_id' => $destination_id,
				'supplier_id' => $facility_id,
				'offer_id' => 0,
				'hash' => $dedupe_hash,
				'status' => 'pending_review',
				'found_at' => current_time( 'mysql' ),
				'reviewed_by' => 0,
				'reviewed_at' => '',
				'source_id' => $source_id,
				'signal_pattern_id' => 0,
				'target_type' => $target_type,
				'target_id' => $target_id,
				'finding_type' => 'neutral',
				'finding_area' => $finding_area,
				'signal_strength' => 'medium',
				'repetition_level' => 'single',
				'verification_status' => 'new',
				'evidence_type' => 'text',
				'evidence_excerpt' => $summary,
				'evidence_url' => $source_url,
				'observed_at' => current_time( 'mysql' ),
				'reviewer_name' => '',
				'reviewer_origin' => '',
				'language' => '',
				'related_collection_task_id' => absint( $task_id ),
				'notes' => sanitize_textarea_field( (string) ( $row['notes'] ?? '' ) ),
			];

			$data = Toptour_Ref_Findings::sanitize_finding_data( $input );
			$validation = Toptour_Ref_Findings::validate_finding_data( $data );
			if ( true !== $validation ) {
				$result['errors']++;
				continue;
			}

			$finding_id = (int) Toptour_Ref_Findings::create_finding( $data );
			if ( $finding_id <= 0 ) {
				$result['errors']++;
				continue;
			}

			$result['created']++;
			if ( $legacy_source_id > 0 ) {
				$result['finding_map'][ $legacy_source_id ] = $finding_id;
			}
		}

		return $result;
	}

	private static function import_photo_candidates( $task_id, $rows, $source_map, $finding_map ) {
		if ( ! is_array( $rows ) ) {
			$rows = [];
		}
		if ( ! is_array( $source_map ) ) {
			$source_map = [];
		}
		if ( ! is_array( $finding_map ) ) {
			$finding_map = [];
		}

		$result = [
			'created' => 0,
			'updated' => 0,
			'errors' => 0,
		];

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				$result['errors']++;
				continue;
			}

			$source_url = esc_url_raw( (string) ( $row['source_url'] ?? '' ) );
			if ( '' === $source_url ) {
				$result['errors']++;
				continue;
			}

			$legacy_source_id = absint( $row['source_id'] ?? 0 );
			$source_id = $legacy_source_id > 0 && isset( $source_map[ $legacy_source_id ] ) ? absint( $source_map[ $legacy_source_id ] ) : absint( $legacy_source_id );
			$finding_id = $legacy_source_id > 0 && isset( $finding_map[ $legacy_source_id ] ) ? absint( $finding_map[ $legacy_source_id ] ) : 0;

			$facility_id = absint( $row['facility_id'] ?? 0 );
			$destination_id = absint( $row['destination_id'] ?? 0 );
			$target_type = $facility_id > 0 ? 'facility' : ( $destination_id > 0 ? 'destination' : 'general' );
			$target_id = $facility_id > 0 ? $facility_id : $destination_id;

			$input = [
				'evidence_title' => 'AI photo evidence candidate',
				'source_id' => $source_id,
				'finding_id' => $finding_id,
				'target_type' => $target_type,
				'target_id' => $target_id,
				'photo_type' => 'platform_photo',
				'comparison_category' => 'unknown',
				'visual_area' => 'other',
				'evidence_url' => $source_url,
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
				'related_collection_task_id' => absint( $task_id ),
				'notes' => self::append_note( (string) ( $row['notes'] ?? '' ), 'AI pending visual review' ),
			];

			if ( self::photo_candidate_exists( $task_id, $source_id, $source_url ) ) {
				$result['updated']++;
				continue;
			}

			$data = Toptour_Ref_Photo_Evidence::sanitize_photo_evidence_data( $input );
			$validation = Toptour_Ref_Photo_Evidence::validate_photo_evidence_data( $data );
			if ( true !== $validation ) {
				$result['errors']++;
				continue;
			}

			$photo_id = Toptour_Ref_Photo_Evidence::create_photo_evidence( $data );
			if ( $photo_id ) {
				$result['created']++;
			} else {
				$result['errors']++;
			}
		}

		return $result;
	}

	private static function fallback_title_from_url( $url ) {
		$url = esc_url_raw( (string) $url );
		if ( '' === $url ) {
			return '';
		}
		$host = wp_parse_url( $url, PHP_URL_HOST );
		if ( ! is_string( $host ) || '' === $host ) {
			return '';
		}
		return sanitize_text_field( $host );
	}

	private static function platform_from_url( $url ) {
		$host = wp_parse_url( (string) $url, PHP_URL_HOST );
		if ( ! is_string( $host ) || '' === $host ) {
			return '';
		}
		return sanitize_text_field( preg_replace( '/^www\./i', '', strtolower( $host ) ) );
	}

	private static function trim_text( $text, $limit ) {
		$text = trim( (string) $text );
		if ( '' === $text ) {
			return '';
		}
		$limit = max( 1, absint( $limit ) );
		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $text, 0, $limit );
		}
		return substr( $text, 0, $limit );
	}

	private static function append_note( $original, $extra ) {
		$original = trim( sanitize_textarea_field( (string) $original ) );
		$extra = trim( sanitize_textarea_field( (string) $extra ) );
		if ( '' === $original ) {
			return $extra;
		}
		if ( '' === $extra ) {
			return $original;
		}
		return $original . "\n" . $extra;
	}

	private static function find_existing_source_id( $url ) {
		$url = esc_url_raw( (string) $url );
		if ( '' === $url ) {
			return 0;
		}

		global $wpdb;
		$table = Toptour_Ref_Reference_Sources::get_table_name();
		$id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE source_url = %s ORDER BY id DESC LIMIT 1", $url ) );
		return absint( $id );
	}

	private static function find_existing_finding_id_by_hash( $hash ) {
		$hash = sanitize_text_field( (string) $hash );
		if ( '' === $hash ) {
			return 0;
		}

		global $wpdb;
		$table = Toptour_Ref_Findings::get_table_name();
		$id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE hash = %s ORDER BY id DESC LIMIT 1", $hash ) );
		return absint( $id );
	}

	private static function build_finding_dedupe_hash( $task_id, $source_url, $summary, $target_type, $target_id ) {
		$signature = [
			absint( $task_id ),
			self::normalize_url_for_dedupe( $source_url ),
			self::normalize_text_for_dedupe( $summary ),
			sanitize_key( (string) $target_type ),
			absint( $target_id ),
		];

		return md5( implode( '|', $signature ) );
	}

	private static function find_existing_finding_id_by_signature( $task_id, $source_url, $summary, $target_type, $target_id ) {
		$task_id = absint( $task_id );
		$target_type = sanitize_key( (string) $target_type );
		$target_id = absint( $target_id );
		$summary_norm = self::normalize_text_for_dedupe( $summary );
		$source_norm = self::normalize_url_for_dedupe( $source_url );

		if ( $task_id <= 0 || '' === $summary_norm ) {
			return 0;
		}

		global $wpdb;
		$table = Toptour_Ref_Findings::get_table_name();
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, excerpt, evidence_excerpt, source_url, evidence_url
				 FROM $table
				 WHERE related_collection_task_id = %d
				 AND target_type = %s
				 AND target_id = %d
				 ORDER BY id DESC
				 LIMIT 200",
				$task_id,
				$target_type,
				$target_id
			)
		);

		if ( ! is_array( $rows ) || empty( $rows ) ) {
			return 0;
		}

		foreach ( $rows as $row ) {
			$existing_summary = self::normalize_text_for_dedupe( (string) ( $row->excerpt ?? '' ) );
			if ( '' === $existing_summary ) {
				$existing_summary = self::normalize_text_for_dedupe( (string) ( $row->evidence_excerpt ?? '' ) );
			}
			if ( '' === $existing_summary || $existing_summary !== $summary_norm ) {
				continue;
			}

			if ( '' === $source_norm ) {
				return absint( $row->id );
			}

			$row_source_norm = self::normalize_url_for_dedupe( (string) ( $row->source_url ?? '' ) );
			if ( '' === $row_source_norm ) {
				$row_source_norm = self::normalize_url_for_dedupe( (string) ( $row->evidence_url ?? '' ) );
			}

			if ( '' !== $row_source_norm && $row_source_norm === $source_norm ) {
				return absint( $row->id );
			}
		}

		return 0;
	}

	private static function normalize_text_for_dedupe( $text ) {
		$text = wp_strip_all_tags( (string) $text );
		$text = remove_accents( $text );
		$text = strtolower( $text );
		$text = preg_replace( '/\s+/u', ' ', trim( $text ) );
		if ( ! is_string( $text ) ) {
			$text = '';
		}

		return sanitize_text_field( $text );
	}

	private static function normalize_url_for_dedupe( $url ) {
		$url = esc_url_raw( (string) $url );
		if ( '' === $url ) {
			return '';
		}

		$host = wp_parse_url( $url, PHP_URL_HOST );
		$path = wp_parse_url( $url, PHP_URL_PATH );
		if ( ! is_string( $host ) || '' === $host ) {
			return sanitize_text_field( strtolower( $url ) );
		}

		$host = preg_replace( '/^www\./i', '', strtolower( $host ) );
		$path = is_string( $path ) ? rtrim( $path, '/' ) : '';

		return sanitize_text_field( $host . $path );
	}

	private static function photo_candidate_exists( $task_id, $source_id, $evidence_url ) {
		$task_id = absint( $task_id );
		$source_id = absint( $source_id );
		$evidence_url = esc_url_raw( (string) $evidence_url );
		if ( $task_id <= 0 || '' === $evidence_url ) {
			return false;
		}

		global $wpdb;
		$table = Toptour_Ref_Photo_Evidence::get_table_name();
		$id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM $table WHERE related_collection_task_id = %d AND source_id = %d AND evidence_url = %s ORDER BY id DESC LIMIT 1",
				$task_id,
				$source_id,
				$evidence_url
			)
		);

		return absint( $id ) > 0;
	}

	private static function move_to_archive( $outbox_file, $original_name ) {
		$paths = Toptour_Ref_AI_Bridge::get_paths();
		$target_name = sanitize_file_name( 'imported-' . (string) $original_name );
		$target = trailingslashit( $paths['archive_dir'] ) . $target_name;
		rename( $outbox_file, $target );
	}

	private static function move_to_error( $outbox_file, $reason, $original_name ) {
		$paths = Toptour_Ref_AI_Bridge::get_paths();
		$target = trailingslashit( $paths['error_dir'] ) . sanitize_file_name( $reason ) . '-' . sanitize_file_name( (string) $original_name );
		rename( $outbox_file, $target );
	}

	private static function claim_file_for_processing( $file_path ) {
		$file_path = (string) $file_path;
		if ( '' === $file_path || ! is_file( $file_path ) ) {
			return [];
		}

		$original_name = sanitize_file_name( basename( $file_path ) );
		$claimed_path = $file_path . '.processing';
		$ok = @rename( $file_path, $claimed_path );
		if ( ! $ok ) {
			return [];
		}

		return [
			'claimed_path' => $claimed_path,
			'original_path' => $file_path,
			'original_name' => $original_name,
		];
	}

	private static function acquire_lock() {
		$token = wp_generate_password( 20, false, false ) . ':' . time();
		$added = add_option( self::LOCK_OPTION_KEY, $token, '', 'no' );
		return $added ? $token : '';
	}

	private static function release_lock( $token ) {
		$token = (string) $token;
		if ( '' === $token ) {
			return;
		}

		$current = get_option( self::LOCK_OPTION_KEY, '' );
		if ( (string) $current === $token ) {
			delete_option( self::LOCK_OPTION_KEY );
		}
	}
}
