<?php
/**
 * Task processor class.
 *
 * Handles manual test runs and automatic cron-driven task processing.
 *
 * @package Toptour_Ref
 * @version 0.2.14
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Toptour_Ref_Task_Processor {
	private const MAX_ATTEMPTS = 3;
	private const RUN_STALE_SECONDS = 1800;

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

		$latest_run = Toptour_Ref_Task_Runs::get_latest_run_for_task( $task->id );
		if ( $latest_run && 'running' === (string) $latest_run->status ) {
			$started_at = strtotime( (string) $latest_run->started_at );
			$age_seconds = $started_at ? max( 0, current_time( 'timestamp' ) - $started_at ) : self::RUN_STALE_SECONDS;

			if ( $age_seconds < self::RUN_STALE_SECONDS ) {
				Toptour_Ref_Task_Events::log_event(
					$task->id,
					'run_deferred_existing_run',
					null,
					[
						'run_id' => (int) $latest_run->id,
						'age_seconds' => $age_seconds,
					],
					'Beh úlohy už prebieha. Nový pokus bol odložený.'
				);
				return [ 'success' => false, 'message' => 'Existing run still running.' ];
			}

			Toptour_Ref_Task_Runs::update_run(
				(int) $latest_run->id,
				[
					'status' => 'failed',
					'finished_at' => current_time( 'mysql' ),
					'error_count' => absint( $latest_run->error_count ) + 1,
					'summary' => 'Stale running run closed automatically.',
				]
			);
			Toptour_Ref_Task_Events::log_event(
				$task->id,
				'run_marked_stale',
				null,
				[
					'run_id' => (int) $latest_run->id,
					'age_seconds' => $age_seconds,
				],
				'Beh bol označený ako zastaraný a uzavretý.'
			);
			Toptour_Ref_Task_Events::log_event(
				$task->id,
				'run_reset_automatic',
				null,
				[
					'run_id' => (int) $latest_run->id,
					'reason' => 'stale_run',
				],
				'Automatický reset odblokoval úlohu po zastaranom behu.'
			);
			Toptour_Ref_Collection_Tasks::touch_task_run( $task->id, 'active' );
		}

		$attempt_number = min( self::MAX_ATTEMPTS, max( 1, absint( $task->attempts ) + 1 ) );
		Toptour_Ref_Task_Events::log_event(
			$task->id,
			'run_attempt_started',
			null,
			[
				'attempt' => $attempt_number,
				'max_attempts' => self::MAX_ATTEMPTS,
				'mode' => $mode,
			],
			'Pokus behu #' . $attempt_number . ' bol spustený.'
		);

		$run_id = Toptour_Ref_Task_Runs::create_run(
			$task->id,
			[
				'status' => 'running',
				'started_at' => current_time( 'mysql' ),
				'summary' => 'Search intake run started (' . $mode . ') - attempt ' . $attempt_number . ' of ' . self::MAX_ATTEMPTS . '.',
			]
		);

		if ( ! $run_id ) {
			self::handle_attempt_failure( $task, null, $attempt_number, 'Task run creation failed.', $mode );
			return [ 'success' => false, 'message' => 'Run creation failed.' ];
		}

		Toptour_Ref_Task_Events::log_event( $task->id, 'run_started', null, [ 'run_id' => $run_id, 'mode' => $mode ], 'Task run started.' );
		Toptour_Ref_Task_Events::log_event( $task->id, 'search_intake_started', null, [ 'run_id' => $run_id, 'mode' => $mode ], 'Search intake bol spustený.' );

		$settings = Toptour_Ref_Search_Provider::get_settings();
		$max_results = max( 1, absint( $settings['max_search_results_per_task'] ?? 15 ) );
		$queries = Toptour_Ref_Search_Provider::build_queries_from_task( $task, 5 );
		foreach ( $queries as $query ) {
			Toptour_Ref_Task_Events::log_event( $task->id, 'search_query_generated', null, [ 'run_id' => $run_id, 'query' => $query ], 'Vygenerovaný vyhľadávací dopyt.' );
		}

		$results = [];
		$candidate_results = Toptour_Ref_Search_Provider::get_existing_candidate_results( $task->id, $max_results );
		if ( ! empty( $candidate_results ) ) {
			$results = array_merge( $results, $candidate_results );
		}

		$provider_reason = '';
		if ( count( $results ) < $max_results ) {
			$provider_type = sanitize_text_field( (string) ( $settings['search_provider_type'] ?? 'existing_candidates_only' ) );
			$provider_enabled = ! empty( $settings['search_provider_enabled'] );

			if ( ! $provider_enabled || 'disabled' === $provider_type ) {
				$provider_reason = 'provider_disabled';
			} elseif ( 'configured_api' === $provider_type ) {
				foreach ( $queries as $query ) {
					$remaining = $max_results - count( $results );
					if ( $remaining <= 0 ) {
						break;
					}

					$api_response = Toptour_Ref_Search_Provider::search_configured_api( $query, $settings, min( 10, $remaining ) );
					if ( empty( $api_response['success'] ) ) {
						$provider_reason = sanitize_text_field( (string) ( $api_response['reason'] ?? 'api_failed' ) );
						Toptour_Ref_Task_Events::log_event( $task->id, 'search_provider_error', null, [ 'run_id' => $run_id, 'query' => $query, 'reason' => $provider_reason ], 'Vyhľadávací provider zlyhal.' );
						continue;
					}

					$results = array_merge( $results, (array) ( $api_response['results'] ?? [] ) );
				}
			}
		}

		if ( empty( $results ) ) {
			if ( '' !== $provider_reason ) {
				Toptour_Ref_Task_Events::log_event( $task->id, 'search_provider_missing', null, [ 'run_id' => $run_id, 'reason' => $provider_reason ], 'Provider nevrátil URL pre intake.' );
			}

			Toptour_Ref_Task_Runs::update_run(
				$run_id,
				[
					'status' => 'finished',
					'finished_at' => current_time( 'mysql' ),
					'found_count' => 0,
					'new_count' => 0,
					'duplicate_count' => 0,
					'error_count' => 0,
					'summary' => 'Search intake finished: no URLs available.',
				]
			);

			Toptour_Ref_Task_Events::log_event( $task->id, 'search_intake_finished', null, [ 'run_id' => $run_id, 'processed' => 0, 'duplicates' => 0, 'errors' => 0 ], 'Search intake bol ukončený bez výsledných URL.' );
			Toptour_Ref_Task_Events::log_event( $task->id, 'run_finished', null, [ 'run_id' => (int) $run_id ], 'Task run finished.' );
			self::update_task_schedule_after_run( $task, true );

			return [
				'success' => true,
				'message' => 'Search intake finished: no URLs available.',
				'run_id' => (int) $run_id,
				'processed_count' => 0,
				'duplicate_count' => 0,
				'error_count' => 0,
				'found_count' => 0,
			];
		}

		$processed_count = 0;
		$duplicate_count = 0;
		$error_count = 0;
		$checked_count = 0;
		$seen_urls = [];

		foreach ( $results as $result ) {
			if ( $checked_count >= $max_results ) {
				break;
			}

			$raw_url = esc_url_raw( (string) ( $result['result_url'] ?? '' ) );
			if ( '' === $raw_url ) {
				continue;
			}

			$normalized = self::normalize_url_for_search( $raw_url );
			if ( '' === $normalized || isset( $seen_urls[ $normalized ] ) ) {
				$duplicate_count++;
				continue;
			}

			$seen_urls[ $normalized ] = true;
			$checked_count++;

			if ( self::is_duplicate_source_or_finding( $task->id, $normalized ) ) {
				$duplicate_count++;
				Toptour_Ref_Task_Events::log_event( $task->id, 'search_result_duplicate', null, [ 'run_id' => $run_id, 'url' => $normalized ], 'URL bola vyhodnotená ako duplikát.' );
				continue;
			}

			Toptour_Ref_Task_Events::log_event( $task->id, 'search_result_found', null, [ 'run_id' => $run_id, 'url' => $normalized, 'query' => sanitize_text_field( (string) ( $result['query_used'] ?? '' ) ) ], 'Nájdený výsledok pre intake.' );
			Toptour_Ref_Task_Events::log_event( $task->id, 'source_sent_to_intake', null, [ 'run_id' => $run_id, 'url' => $normalized ], 'Zdroj bol odoslaný do Data Intake Router.' );

			$intake_input = [
				'task_id' => absint( $task->id ),
				'run_id' => absint( $run_id ),
				'source_url' => $normalized,
				'destination_id' => absint( $task->destination_id ?? 0 ),
				'facility_id' => absint( $task->supplier_id ?? 0 ),
				'offer_id' => absint( $task->offer_id ?? 0 ),
				'input_type' => 'auto',
				'manager_note' => sanitize_textarea_field( (string) ( $result['result_snippet'] ?? '' ) ),
			];

			$intake_result = Toptour_Ref_Data_Intake_Router::process_manual_intake( $intake_input );
			if ( empty( $intake_result['success'] ) ) {
				$error_count++;
				$error_message = sanitize_text_field( (string) ( $intake_result['message'] ?? 'Intake failed.' ) );
				Toptour_Ref_Task_Events::log_event( $task->id, 'intake_failed', null, [ 'run_id' => $run_id, 'url' => $normalized ], $error_message );
				if ( false !== stripos( $error_message, 'http' ) || false !== stripos( $error_message, 'fetch' ) ) {
					Toptour_Ref_Task_Events::log_event( $task->id, 'source_fetch_failed', null, [ 'run_id' => $run_id, 'url' => $normalized ], $error_message );
				}
				continue;
			}

			$processed_count++;
			$details = (array) ( $intake_result['details'] ?? [] );
			Toptour_Ref_Task_Events::log_event( $task->id, 'source_ingested', null, [ 'run_id' => $run_id, 'url' => $normalized, 'source_id' => absint( $details['source_id'] ?? 0 ) ], 'Zdroj bol úspešne ingestovaný.' );

			if ( ! empty( $details['finding_created'] ) ) {
				Toptour_Ref_Task_Events::log_event( $task->id, 'finding_created', null, [ 'run_id' => $run_id, 'finding_id' => absint( $details['finding_id'] ?? 0 ) ], 'Vytvorený finding zo search intake.' );
			}
			if ( ! empty( $details['offer_created'] ) ) {
				Toptour_Ref_Task_Events::log_event( $task->id, 'offer_created', null, [ 'run_id' => $run_id, 'offer_id' => absint( $details['offer_id'] ?? 0 ) ], 'Vytvorená ponuka zo search intake.' );
			}
			if ( ! empty( $details['offer_updated'] ) ) {
				Toptour_Ref_Task_Events::log_event( $task->id, 'offer_updated', null, [ 'run_id' => $run_id, 'offer_id' => absint( $details['offer_id'] ?? 0 ) ], 'Aktualizovaná ponuka zo search intake.' );
			}
		}

		$run_status = $processed_count > 0 || 0 === $error_count ? 'finished' : 'failed';
		Toptour_Ref_Task_Runs::update_run(
			$run_id,
			[
				'status' => $run_status,
				'finished_at' => current_time( 'mysql' ),
				'found_count' => $checked_count,
				'new_count' => $processed_count,
				'duplicate_count' => $duplicate_count,
				'error_count' => $error_count,
				'summary' => sprintf( 'Search intake done. processed=%d duplicates=%d errors=%d', $processed_count, $duplicate_count, $error_count ),
			]
		);

		Toptour_Ref_Task_Events::log_event( $task->id, 'search_intake_finished', null, [ 'run_id' => $run_id, 'processed' => $processed_count, 'duplicates' => $duplicate_count, 'errors' => $error_count ], 'Search intake bol ukončený.' );
		Toptour_Ref_Task_Events::log_event( $task->id, 'run_finished', null, [ 'run_id' => (int) $run_id ], 'Task run finished.' );

		if ( 'failed' === $run_status ) {
			self::handle_attempt_failure( $task, $run_id, $attempt_number, 'Search intake failed for all URLs.', $mode );
			return [
				'success' => false,
				'message' => 'Search intake failed for all URLs.',
				'run_id' => (int) $run_id,
				'processed_count' => $processed_count,
				'duplicate_count' => $duplicate_count,
				'error_count' => $error_count,
				'found_count' => $checked_count,
			];
		}

		self::update_task_schedule_after_run( $task, true );

		return [
			'success' => true,
			'message' => 'Search intake completed.',
			'run_id' => (int) $run_id,
			'processed_count' => $processed_count,
			'duplicate_count' => $duplicate_count,
			'error_count' => $error_count,
			'found_count' => $checked_count,
		];
	}

	private static function normalize_url_for_search( $url ) {
		$parts = wp_parse_url( trim( (string) $url ) );
		if ( ! is_array( $parts ) || empty( $parts['host'] ) ) {
			return '';
		}

		$scheme = isset( $parts['scheme'] ) ? strtolower( (string) $parts['scheme'] ) : 'https';
		$host = strtolower( (string) $parts['host'] );
		$path = isset( $parts['path'] ) ? rtrim( (string) $parts['path'], '/' ) : '';
		if ( '' === $path ) {
			$path = '/';
		}

		$query = '';
		if ( ! empty( $parts['query'] ) ) {
			parse_str( (string) $parts['query'], $query_args );
			if ( is_array( $query_args ) ) {
				foreach ( array_keys( $query_args ) as $key ) {
					if ( 0 === strpos( (string) $key, 'utm_' ) ) {
						unset( $query_args[ $key ] );
					}
				}
				ksort( $query_args );
				$query = http_build_query( $query_args );
			}
		}

		return $scheme . '://' . $host . $path . ( '' !== $query ? '?' . $query : '' );
	}

	private static function is_duplicate_source_or_finding( $task_id, $normalized_url ) {
		global $wpdb;
		$task_id = absint( $task_id );
		$normalized_url = trim( (string) $normalized_url );
		if ( '' === $normalized_url ) {
			return true;
		}

		$source_table = Toptour_Ref_Reference_Sources::get_table_name();
		$finding_table = Toptour_Ref_Findings::get_table_name();
		$host = wp_parse_url( $normalized_url, PHP_URL_HOST );

		if ( $host ) {
			$source_rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT source_url FROM $source_table WHERE source_url LIKE %s ORDER BY id DESC LIMIT 300",
					'%' . $wpdb->esc_like( (string) $host ) . '%'
				)
			);
			foreach ( (array) $source_rows as $row ) {
				if ( self::normalize_url_for_search( (string) $row->source_url ) === $normalized_url ) {
					return true;
				}
			}
		}

		$finding_exists = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(1) FROM $finding_table WHERE related_collection_task_id = %d AND source_url <> ''",
				$task_id
			)
		);
		if ( $finding_exists > 0 && $host ) {
			$finding_rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT source_url FROM $finding_table WHERE related_collection_task_id = %d AND source_url LIKE %s ORDER BY id DESC LIMIT 300",
					$task_id,
					'%' . $wpdb->esc_like( (string) $host ) . '%'
				)
			);
			foreach ( (array) $finding_rows as $row ) {
				if ( self::normalize_url_for_search( (string) $row->source_url ) === $normalized_url ) {
					return true;
				}
			}
		}

		return false;
	}

	private static function finish_run_with_error( $task_id, $run_id, $message ) {
		if ( $run_id ) {
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
		Toptour_Ref_Task_Events::log_event( $task_id, 'error', null, [ 'run_id' => (int) $run_id ], sanitize_text_field( $message ) );
	}

	private static function handle_attempt_failure( $task, $run_id, $attempt_number, $message, $mode = 'manual' ) {
		if ( ! $task ) {
			return;
		}

		$attempt_number = max( 1, min( self::MAX_ATTEMPTS, absint( $attempt_number ) ) );
		$task_id = absint( $task->id );
		$now = current_time( 'mysql' );
		$is_final_attempt = $attempt_number >= self::MAX_ATTEMPTS;

		if ( ! $run_id ) {
			Toptour_Ref_Task_Events::log_event( $task_id, 'error', null, [ 'attempt' => $attempt_number ], sanitize_text_field( $message ) );
		}

		Toptour_Ref_Task_Events::log_event(
			$task_id,
			'run_suspect',
			[
				'attempt' => $attempt_number,
				'run_id' => (int) $run_id,
			],
			null,
			'Beh úlohy sa javí ako podozrivý.'
		);

		global $wpdb;
		$update = [
			'attempts' => $attempt_number,
			'last_run_at' => $now,
			'updated_at' => $now,
		];

		$can_auto_retry = 'automatic' === $mode || 'active' === (string) ( $task->task_status ?? '' );

		if ( $is_final_attempt ) {
			$update['task_status'] = 'failed';
			$update['next_run_at'] = null;
			Toptour_Ref_Task_Events::log_event( $task_id, 'run_failed_max_attempts', null, [ 'attempt' => $attempt_number, 'run_id' => (int) $run_id ], 'Beh úlohy zlyhal po troch neúspešných pokusoch.' );
		} elseif ( $can_auto_retry ) {
			$update['task_status'] = 'active';
			$update['next_run_at'] = $now;
			Toptour_Ref_Task_Events::log_event( $task_id, 'run_reset_automatic', null, [ 'attempt' => $attempt_number, 'next_attempt' => $attempt_number + 1, 'run_id' => (int) $run_id ], 'Automatický reset pripravil ďalší pokus.' );
			Toptour_Ref_Task_Events::log_event( $task_id, 'run_retry_scheduled', null, [ 'attempt' => $attempt_number + 1, 'run_id' => (int) $run_id ], 'Ďalší pokus bol naplánovaný.' );
		} else {
			$update['next_run_at'] = null;
		}

		$wpdb->update(
			Toptour_Ref_Collection_Tasks::get_table_name(),
			$update,
			[ 'id' => $task_id ]
		);
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
			'attempts' => 0,
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

		if ( $successful && 'failed' === (string) ( $task->task_status ?? '' ) ) {
			$update['task_status'] = 'active';
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
