<?php
/**
 * Task processor class.
 *
 * Handles manual test runs and automatic cron-driven task processing.
 *
 * @package Toptour_Ref
 * @version 0.2.5
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Toptour_Ref_Task_Processor {

	public static function get_mode() {
		$mode = sanitize_text_field( (string) get_option( 'toptour_ref_finder_mode', 'manual' ) );
		return in_array( $mode, [ 'manual', 'automatic' ], true ) ? $mode : 'manual';
	}

	public static function set_mode( $mode ) {
		$mode = sanitize_text_field( (string) $mode );
		if ( ! in_array( $mode, [ 'manual', 'automatic' ], true ) ) {
			$mode = 'manual';
		}
		update_option( 'toptour_ref_finder_mode', $mode );
		return $mode;
	}

	public static function process_scheduled_tasks() {
		if ( self::get_mode() !== 'automatic' ) {
			return 0;
		}

		global $wpdb;
		$table = Toptour_Ref_Collection_Tasks::get_table_name();
		$now = current_time( 'mysql' );

		$tasks = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $table WHERE task_status = %s AND frequency != %s AND (next_run_at IS NULL OR next_run_at <= %s) ORDER BY id ASC",
				'active',
				'manual',
				$now
			)
		);

		$processed = 0;
		foreach ( (array) $tasks as $task ) {
			$result = self::process_task( (int) $task->id, 'automatic' );
			if ( ! empty( $result['success'] ) ) {
				$processed++;
			}
		}

		return $processed;
	}

	public static function process_task( $task_id, $mode = 'manual' ) {
		$task = Toptour_Ref_Collection_Tasks::get_task( absint( $task_id ) );
		if ( ! $task ) {
			return [ 'success' => false, 'message' => 'Task not found.' ];
		}

		$mode = in_array( $mode, [ 'manual', 'automatic' ], true ) ? $mode : 'manual';
		if ( 'automatic' === $mode && (string) $task->frequency === 'manual' ) {
			Toptour_Ref_Task_Events::log_event( $task->id, 'run_skipped', null, null, 'Automatic processing skipped: task frequency is manual.' );
			return [ 'success' => false, 'message' => 'Skipped: manual frequency task.' ];
		}

		if ( 'automatic' === $mode && ! in_array( (string) $task->frequency, [ 'daily', 'twice_daily', 'three_times_daily', 'six_daily' ], true ) ) {
			Toptour_Ref_Task_Events::log_event( $task->id, 'run_skipped', null, [ 'frequency' => (string) $task->frequency ], 'Automatic processing skipped: unknown frequency.' );
			return [ 'success' => false, 'message' => 'Skipped: unknown frequency.' ];
		}

		$run_id = Toptour_Ref_Task_Runs::create_run(
			$task->id,
			[
				'status' => 'running',
				'started_at' => current_time( 'mysql' ),
				'summary' => 'Reference analysis run started (' . $mode . ').',
			]
		);

		if ( ! $run_id ) {
			Toptour_Ref_Task_Events::log_event( $task->id, 'error', null, null, 'Task run creation failed.' );
			return [ 'success' => false, 'message' => 'Run creation failed.' ];
		}

		Toptour_Ref_Task_Events::log_event( $task->id, 'run_started', null, [ 'run_id' => $run_id, 'mode' => $mode ], 'Task run started.' );

		$now = current_time( 'mysql' );
		$hash = md5( implode( '|', [ $task->id, $run_id, sanitize_text_field( $task->task_title ), sanitize_text_field( $task->query_text ) ] ) );

		$finding_id = Toptour_Ref_Findings::create_finding(
			[
				'finding_title' => 'Internal analysis placeholder for task #' . absint( $task->id ),
				'task_id' => absint( $task->id ),
				'run_id' => absint( $run_id ),
				'source_url' => '',
				'source_title' => sanitize_text_field( $task->task_title ),
				'source_type' => 'other',
				'excerpt' => '',
				'detected_sentiment' => 'neutral',
				'review_published_at' => '',
				'analysis_performed_at' => $now,
				'source_detected_at' => $now,
				'source_last_checked_at' => $now,
				'reference_language' => 'unknown',
				'reference_type' => 'other',
				'analysis_summary' => 'Internal placeholder analysis only. No external scraping or citation storage.',
				'analysis_status' => 'analyzed',
				'confidence_score' => 50,
				'destination_mapping_note' => '',
				'poi_extraction_note' => 'No automatic POI extraction in this phase.',
				'offer_relation_note' => '',
				'poi_candidate_id' => 0,
				'destination_id' => absint( $task->destination_id ?? 0 ),
				'supplier_id' => absint( $task->supplier_id ?? 0 ),
				'offer_id' => absint( $task->offer_id ?? 0 ),
				'hash' => $hash,
				'status' => 'new',
				'found_at' => $now,
				'reviewed_by' => 0,
				'reviewed_at' => '',
				'source_id' => 0,
				'signal_pattern_id' => 0,
				'target_type' => 'collection_task',
				'target_id' => absint( $task->id ),
				'finding_type' => 'neutral',
				'finding_area' => 'other',
				'signal_strength' => 'weak',
				'repetition_level' => 'single',
				'verification_status' => 'new',
				'evidence_type' => 'own_observation',
				'evidence_excerpt' => 'Internal run placeholder created for lifecycle verification.',
				'evidence_url' => '',
				'observed_at' => $now,
				'reviewer_name' => '',
				'reviewer_origin' => 'internal',
				'language' => 'unknown',
				'related_collection_task_id' => absint( $task->id ),
				'notes' => 'Task lifecycle internal test record.',
			]
		);

		if ( ! $finding_id ) {
			self::finish_run_with_error( $task->id, $run_id, 'Finding creation failed.' );
			return [ 'success' => false, 'message' => 'Finding creation failed.' ];
		}

		Toptour_Ref_Task_Events::log_event( $task->id, 'finding_added', null, [ 'finding_id' => (int) $finding_id ], 'Lifecycle finding created.' );
		Toptour_Ref_Task_Events::log_event( $task->id, 'reference_analysis_created', null, [ 'finding_id' => (int) $finding_id, 'run_id' => (int) $run_id ], 'Reference analysis metadata created.' );

		$snapshot_id = false;
		if ( absint( $task->offer_id ?? 0 ) > 0 || ! empty( $task->query_text ) ) {
			$snapshot_hash = md5( implode( '|', [ $task->id, $task->offer_id, $task->query_text, gmdate( 'Y-m-d-H' ) ] ) );
			$snapshot_id = Toptour_Ref_Offer_Snapshots::create_snapshot(
				[
					'finding_id' => (int) $finding_id,
					'task_id' => (int) $task->id,
					'run_id' => (int) $run_id,
					'offer_id' => absint( $task->offer_id ?? 0 ),
					'supplier_id' => absint( $task->supplier_id ?? 0 ),
					'destination_id' => absint( $task->destination_id ?? 0 ),
					'source_url' => '',
					'source_title' => sanitize_text_field( $task->task_title ),
					'offer_name' => sanitize_text_field( $task->task_title ),
					'offer_description_summary' => 'Internal summary placeholder. No external citation copied.',
					'price_value' => null,
					'price_currency' => '',
					'price_note' => 'Price unavailable in internal placeholder mode.',
					'stay_duration' => '',
					'persons_min' => 0,
					'persons_max' => 0,
					'season' => '',
					'meal_plan' => '',
					'transport_type' => '',
					'accommodation_type' => '',
					'facility_category' => '',
					'included_services_summary' => '',
					'excluded_services_summary' => '',
					'availability_note' => '',
					'booking_conditions_summary' => '',
					'public_offer_published_at' => '',
					'source_detected_at' => $now,
					'source_last_checked_at' => $now,
					'analysis_performed_at' => $now,
					'snapshot_hash' => $snapshot_hash,
					'status' => 'new',
				]
			);

			if ( $snapshot_id ) {
				Toptour_Ref_Task_Events::log_event( $task->id, 'offer_snapshot_created', null, [ 'snapshot_id' => (int) $snapshot_id, 'finding_id' => (int) $finding_id ], 'Offer snapshot created.' );
				Toptour_Ref_Offer_Snapshots::mark_previous_snapshots_superseded( (int) $task->id, absint( $task->offer_id ?? 0 ), (int) $snapshot_id );
			}
		}

		Toptour_Ref_Task_Events::log_event( $task->id, 'poi_candidate_suggested', null, [ 'finding_id' => (int) $finding_id ], 'POI candidate is only prepared as TODO in this phase.' );

		Toptour_Ref_Task_Runs::update_run(
			$run_id,
			[
				'status' => 'finished',
				'finished_at' => current_time( 'mysql' ),
				'found_count' => 1,
				'new_count' => 1,
				'duplicate_count' => 0,
				'error_count' => 0,
				'summary' => 'Internal lifecycle run completed.',
			]
		);

		Toptour_Ref_Task_Events::log_event( $task->id, 'run_finished', null, [ 'run_id' => (int) $run_id ], 'Task run finished.' );
		self::update_task_schedule_after_run( $task, true );

		return [
			'success' => true,
			'message' => 'Task lifecycle run completed.',
			'run_id' => (int) $run_id,
			'finding_id' => (int) $finding_id,
			'snapshot_id' => $snapshot_id ? (int) $snapshot_id : 0,
		];
	}

	private static function finish_run_with_error( $task_id, $run_id, $message ) {
		Toptour_Ref_Task_Runs::update_run(
			$run_id,
			[
				'status' => 'failed',
				'finished_at' => current_time( 'mysql' ),
				'error_count' => 1,
				'summary' => sanitize_text_field( $message ),
			]
		);
		Toptour_Ref_Task_Events::log_event( $task_id, 'error', null, [ 'run_id' => (int) $run_id ], sanitize_text_field( $message ) );
		self::update_task_schedule_after_run( Toptour_Ref_Collection_Tasks::get_task( $task_id ), false );
	}

	private static function update_task_schedule_after_run( $task, $successful ) {
		if ( ! $task ) {
			return;
		}

		$frequency = sanitize_text_field( (string) ( $task->frequency ?? 'manual' ) );
		$next_run = self::calculate_next_run_at( $frequency );

		global $wpdb;
		$update = [
			'last_run_at' => current_time( 'mysql' ),
			'updated_at' => current_time( 'mysql' ),
		];

		if ( null === $next_run ) {
			if ( ! in_array( $frequency, [ 'manual', 'daily', 'twice_daily', 'three_times_daily', 'six_daily' ], true ) ) {
				$update['task_status'] = 'needs_review';
				Toptour_Ref_Task_Events::log_event( $task->id, 'run_skipped', null, [ 'frequency' => $frequency ], 'Unknown frequency, task skipped safely.' );
			}
			$update['next_run_at'] = null;
		} else {
			$update['next_run_at'] = $next_run;
		}

		if ( ! $successful ) {
			$update['task_status'] = 'failed';
		}

		$wpdb->update(
			Toptour_Ref_Collection_Tasks::get_table_name(),
			$update,
			[ 'id' => absint( $task->id ) ]
		);
	}

	private static function calculate_next_run_at( $frequency ) {
		$ts = current_time( 'timestamp' );

		switch ( $frequency ) {
			case 'manual':
				return null;
			case 'daily':
				$ts = strtotime( '+1 day', $ts );
				break;
			case 'twice_daily':
				$ts = strtotime( '+12 hours', $ts );
				break;
			case 'three_times_daily':
				$ts = strtotime( '+8 hours', $ts );
				break;
			case 'six_daily':
				$ts = strtotime( '+4 hours', $ts );
				break;
			default:
				return null;
		}

		return gmdate( 'Y-m-d H:i:s', $ts + ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) );
	}
}
