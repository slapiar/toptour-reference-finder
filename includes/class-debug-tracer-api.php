<?php
/**
 * Debug Tracer REST API class.
 *
 * Handles REST endpoints for step-by-step AI process tracing.
 *
 * @package Toptour_Ref
 * @version 0.2.14
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Debug Tracer REST API endpoints.
 */
class Toptour_Ref_Debug_Tracer_API {

	const NAMESPACE = 'toptour/v1';

	/**
	 * Register tracer API routes.
	 *
	 * @return void
	 */
	public static function register_routes() {
		add_filter( 'rest_post_dispatch', array( __CLASS__, 'disable_cache_for_tracer_responses' ), 10, 3 );
		add_filter( 'rest_pre_serve_request', array( __CLASS__, 'send_no_cache_headers_for_tracer' ), 10, 4 );

		// Initialize tracer run
		register_rest_route(
			self::NAMESPACE,
			'/tracer/initialize',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'initialize' ),
				'permission_callback' => array( __CLASS__, 'check_permissions' ),
				'args'                => array(
					'task_id' => array(
						'required' => true,
						'type'    => 'integer',
						'validate_callback' => 'rest_validate_request_arg',
					),
				),
			)
		);

		// Generate batch
		register_rest_route(
			self::NAMESPACE,
			'/tracer/generate-batch',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'generate_batch' ),
				'permission_callback' => array( __CLASS__, 'check_permissions' ),
				'args'                => array(
					'task_id' => array(
						'required' => true,
						'type'    => 'integer',
					),
					'tracer_run_id' => array(
						'required' => true,
						'type'    => 'string',
					),
					'supplemental_context' => array(
						'required' => false,
						'type'    => 'string',
					),
					'supplemental_history' => array(
						'required' => false,
						'type'    => 'array',
					),
				),
			)
		);

		// Process AI
		register_rest_route(
			self::NAMESPACE,
			'/tracer/process-ai',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'process_ai' ),
				'permission_callback' => array( __CLASS__, 'check_permissions' ),
				'args'                => array(
					'task_id' => array(
						'required' => true,
						'type'    => 'integer',
					),
					'batch_id' => array(
						'required' => true,
						'type'    => 'string',
					),
					'tracer_run_id' => array(
						'required' => true,
						'type'    => 'string',
					),
				),
			)
		);

		// Import results
		register_rest_route(
			self::NAMESPACE,
			'/tracer/import-results',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'import_results' ),
				'permission_callback' => array( __CLASS__, 'check_permissions' ),
				'args'                => array(
					'task_id' => array(
						'required' => true,
						'type'    => 'integer',
					),
					'batch_id' => array(
						'required' => true,
						'type'    => 'string',
					),
					'tracer_run_id' => array(
						'required' => true,
						'type'    => 'string',
					),
				),
			)
		);

		// Cleanup tracer artifacts
		register_rest_route(
			self::NAMESPACE,
			'/tracer/cleanup-run',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'cleanup_run' ),
				'permission_callback' => array( __CLASS__, 'check_permissions' ),
				'args'                => array(
					'task_id' => array(
						'required' => true,
						'type'    => 'integer',
					),
					'batch_id' => array(
						'required' => false,
						'type'    => 'string',
					),
					'tracer_run_id' => array(
						'required' => false,
						'type'    => 'string',
					),
					'reason' => array(
						'required' => false,
						'type'    => 'string',
					),
					'cleanup_scope' => array(
						'required' => false,
						'type'    => 'string',
					),
				),
			)
		);
	}

	/**
	 * Check REST API permissions.
	 *
	 * @return bool
	 */
	public static function check_permissions() {
		return current_user_can( 'manage_toptour_references' );
	}

	public static function disable_cache_for_tracer_responses( $response, $server, $request ) {
		if ( ! self::is_tracer_route_request( $request ) ) {
			return $response;
		}

		if ( $response instanceof WP_REST_Response ) {
			$response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0' );
			$response->header( 'Pragma', 'no-cache' );
			$response->header( 'Expires', '0' );
			$response->header( 'X-LiteSpeed-Cache-Control', 'no-cache' );
		}

		return $response;
	}

	public static function send_no_cache_headers_for_tracer( $served, $result, $request, $server ) {
		if ( ! self::is_tracer_route_request( $request ) ) {
			return $served;
		}

		nocache_headers();
		header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );
		header( 'X-LiteSpeed-Cache-Control: no-cache' );

		return $served;
	}

	private static function is_tracer_route_request( $request ) {
		if ( ! is_object( $request ) || ! method_exists( $request, 'get_route' ) ) {
			return false;
		}

		$route = (string) $request->get_route();
		return 0 === strpos( $route, '/' . self::NAMESPACE . '/tracer/' );
	}

	/**
	 * Step 1: Initialize tracer.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public static function initialize( $request ) {
		$task_id = absint( $request->get_param( 'task_id' ) );

		if ( $task_id <= 0 ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => 'Neplatné ID úlohy.',
				),
				400
			);
		}

		$task = Toptour_Ref_Collection_Tasks::get_task( $task_id );
		if ( ! $task ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => 'Úloha nenájdená.',
				),
				404
			);
		}

		// Generate tracer run ID
		$tracer_run_id = 'tracer-' . $task_id . '-' . wp_generate_uuid4();

		// Store tracer session
		set_transient( 'toptour_tracer_' . $tracer_run_id, array(
			'task_id' => $task_id,
			'created_at' => current_time( 'mysql' ),
			'steps' => array(),
		), HOUR_IN_SECONDS );

		$settings = Toptour_Ref_AI_Bridge::get_settings();

		return new WP_REST_Response(
			array(
				'success' => true,
				'tracer_run_id' => $tracer_run_id,
				'task' => array(
					'id' => $task->id,
					'title' => $task->task_title,
					'destination' => Toptour_Ref_Collection_Tasks::get_destination_label( $task ),
				),
				'config' => array(
					'ai_enabled' => $settings['ai_bridge_enabled'],
					'ai_model' => $settings['ai_model'],
					'max_tokens' => $settings['ai_max_tokens'],
					'temperature' => $settings['ai_temperature'],
					'batch_limit' => $settings['ai_batch_limit'],
				),
			),
			200
		);
	}

	/**
	 * Step 2: Generate batch.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public static function generate_batch( $request ) {
		$task_id = absint( $request->get_param( 'task_id' ) );
		$tracer_run_id = sanitize_text_field( $request->get_param( 'tracer_run_id' ) );
		$supplemental_context = sanitize_textarea_field( $request->get_param( 'supplemental_context' ) );
		$supplemental_history_raw = $request->get_param( 'supplemental_history' );
		$supplemental_history = array();
		if ( is_array( $supplemental_history_raw ) ) {
			foreach ( $supplemental_history_raw as $entry ) {
				$clean_entry = sanitize_textarea_field( (string) $entry );
				if ( '' !== trim( $clean_entry ) ) {
					$supplemental_history[] = $clean_entry;
				}
			}
		}
		if ( empty( $supplemental_history ) && '' !== $supplemental_context ) {
			$supplemental_history[] = $supplemental_context;
		}

		if ( $task_id <= 0 ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => 'Neplatné ID úlohy.',
				),
				400
			);
		}

		if ( ! empty( $supplemental_history ) ) {
			self::append_supplemental_history_to_task_notes( $task_id, $tracer_run_id, $supplemental_history );
		}

		// Generate batch using existing bridge
		$batch_result = Toptour_Ref_AI_Bridge::generate_inbox_batch( $task_id );

		if ( empty( $batch_result['ok'] ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => $batch_result['message'] ?? 'Generovanie batchu zlyhalo.',
				),
				400
			);
		}

		// Read generated batch file to get payload
		$batch_payload = array();
		$record_count = 0;

		$paths = Toptour_Ref_AI_Bridge::get_paths();
		$batch_file = trailingslashit( $paths['inbox_dir'] ) . $batch_result['filename'];

		if ( file_exists( $batch_file ) ) {
			$raw = file_get_contents( $batch_file );
			$batch_payload = json_decode( $raw, true );
			if ( is_array( $batch_payload ) && ! empty( $supplemental_history ) ) {
				$compiled_supplement = self::compile_supplemental_history_text( $supplemental_history );
				$batch_payload['question'] = trim( (string) ( $batch_payload['question'] ?? '' ) . "\n\nDOPLŇUJÚCE UPRESNENIA OD MANAŽÉRA (SEKVENČNE):\n" . $compiled_supplement );
				if ( ! isset( $batch_payload['context'] ) || ! is_array( $batch_payload['context'] ) ) {
					$batch_payload['context'] = array();
				}
				$batch_payload['context']['tracer_supplemental_context'] = end( $supplemental_history );
				$batch_payload['context']['tracer_supplemental_context_history'] = array_values( $supplemental_history );
				$batch_payload['context']['tracer_supplemental_context_count'] = count( $supplemental_history );
				file_put_contents( $batch_file, wp_json_encode( $batch_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
			}
			if ( is_array( $batch_payload ) && isset( $batch_payload['input']['records'] ) ) {
				$record_count = count( (array) $batch_payload['input']['records'] );
			}
		}

		// Extract batch ID
		$batch_id = $batch_result['batch_id'] ?? pathinfo( $batch_result['filename'], PATHINFO_FILENAME );

		return new WP_REST_Response(
			array(
				'success' => true,
				'batch_id' => $batch_id,
				'filename' => $batch_result['filename'],
				'record_count' => $record_count,
				'batch_payload' => $batch_payload,
			),
			200
		);
	}

	/**
	 * Step 3: Process AI.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public static function process_ai( $request ) {
		$task_id = absint( $request->get_param( 'task_id' ) );
		$batch_id = sanitize_text_field( $request->get_param( 'batch_id' ) );

		if ( $task_id <= 0 ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => 'Neplatné ID úlohy.',
				),
				400
			);
		}

		// Get AI Bridge settings
		$settings = Toptour_Ref_AI_Bridge::get_settings();
		if ( empty( $settings['ai_bridge_enabled'] ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => 'AI Bridge nie je povolený.',
				),
				400
			);
		}

		$paths = Toptour_Ref_AI_Bridge::get_paths();
		$pending_inbox = trailingslashit( $paths['inbox_dir'] ) . sanitize_file_name( $batch_id ) . '.json';
		$existing_outbox = self::find_outbox_file_for_batch( $batch_id );

		// If inbox still exists for this batch, force a fresh bridge pass to avoid stale outbox reuse.
		if ( file_exists( $pending_inbox ) ) {
			$existing_outbox = '';
		}

		if ( '' === $existing_outbox ) {
			$bridge_result = Toptour_Ref_AI_Bridge::process_pending_batches( max( 10, absint( $settings['ai_batch_limit'] ?? 1 ) ) );

			if ( empty( $bridge_result['success'] ) ) {
				return new WP_REST_Response(
					array(
						'success' => false,
						'message' => $bridge_result['message'] ?? 'AI Bridge spracovanie zlyhalo.',
					),
					400
				);
			}

			$existing_outbox = self::find_outbox_file_for_batch( $batch_id );
		}

		if ( '' === $existing_outbox || ! file_exists( $existing_outbox ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => 'Pre batch sa nenašla AI odpoveď v outboxe.',
				),
				404
			);
		}

		$ai_response = self::read_json_file( $existing_outbox );
		if ( ! is_array( $ai_response ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => 'AI odpoveď v outboxe nie je validný JSON.',
				),
				500
			);
		}

		$input_batch_id = sanitize_text_field( (string) ( $ai_response['input']['batch_id'] ?? '' ) );
		$input_task_id = absint( $ai_response['input']['task_id'] ?? 0 );
		if ( '' !== $input_batch_id && $input_batch_id !== $batch_id ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => 'Outbox batch mismatch: požadovaný batch sa nezhoduje s obsahom outbox súboru.',
					'diagnostics' => array(
						'requested_batch_id' => $batch_id,
						'outbox_input_batch_id' => $input_batch_id,
						'outbox_file' => basename( $existing_outbox ),
					),
				),
				409
			);
		}

		if ( $input_task_id > 0 && $input_task_id !== $task_id ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => 'Outbox task mismatch: outbox patrí inej úlohe.',
					'diagnostics' => array(
						'requested_task_id' => $task_id,
						'outbox_input_task_id' => $input_task_id,
						'outbox_file' => basename( $existing_outbox ),
					),
				),
				409
			);
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'batch_id' => $batch_id,
				'ai_model' => $ai_response['ai']['model'] ?? $settings['ai_model'],
				'tokens_used' => 0,
				'outbox_file' => basename( $existing_outbox ),
				'generated_at' => sanitize_text_field( (string) ( $ai_response['generated_at'] ?? '' ) ),
				'ai_response' => $ai_response,
			),
			200
		);
	}

	/**
	 * Step 4: Import results.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public static function import_results( $request ) {
		$task_id = absint( $request->get_param( 'task_id' ) );
		$batch_id = sanitize_text_field( $request->get_param( 'batch_id' ) );

		if ( $task_id <= 0 ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => 'Neplatné ID úlohy.',
				),
				400
			);
		}

		// Run the actual import process
		$import_result = Toptour_Ref_AI_Outbox_Importer::process_pending_outbox( 10 );
		$latest_report = array();
		$reports = Toptour_Ref_AI_Outbox_Importer::get_import_reports( 10 );

		if ( is_array( $reports ) ) {
			foreach ( $reports as $report ) {
				if ( ! is_array( $report ) ) {
					continue;
				}

				if ( absint( $report['task_id'] ?? 0 ) === $task_id ) {
					$latest_report = $report;
					break;
				}
			}
		}

		// Collect created records
		$findings = array();
		$photos = array();
		$task = Toptour_Ref_Collection_Tasks::get_task( $task_id );

		if ( $task ) {
			// Get recent findings for this task (if method exists)
			if ( method_exists( 'Toptour_Ref_Findings', 'get_findings_by_task' ) ) {
				$findings = Toptour_Ref_Findings::get_findings_by_task( $task_id, 5 );
			}
			
			// Get recent photos for this task
			$recent_photos = Toptour_Ref_Photo_Evidence::get_photo_evidence_list(
				array(
					'target_type' => 'collection_task',
					'per_page' => 10,
				)
			);
			
			if ( is_array( $recent_photos ) ) {
				foreach ( $recent_photos as $photo ) {
					if ( absint( $photo->related_collection_task_id ?? 0 ) !== $task_id ) {
						continue;
					}

					$photo_url = $photo->evidence_url ?? '';
					$thumbnail_url = $photo->thumbnail_url ?? $photo_url;
					if ( '' === $photo_url && '' === $thumbnail_url ) {
						continue;
					}

					$photos[] = array(
						'id' => $photo->id ?? 0,
						'description' => $photo->evidence_title ?? $photo->description ?? '',
						'photo_url' => $photo_url,
						'thumbnail_url' => $thumbnail_url,
						'url' => $photo_url,
						'finding' => $photo->observation_summary ?? '',
					);
				}
			}
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'batch_id' => $batch_id,
				'import_message' => $latest_report['message'] ?? ( $import_result['message'] ?? '' ),
				'import_metrics' => is_array( $latest_report['metrics'] ?? null ) ? $latest_report['metrics'] : array(),
				'module_metrics' => is_array( $latest_report['module_metrics'] ?? null ) ? $latest_report['module_metrics'] : array(),
				'findings_created' => count( (array) $findings ),
				'photos_created' => count( $photos ),
				'sources_processed' => $import_result['processed'] ?? 0,
				'findings' => $findings,
				'photos' => $photos,
			),
			200
		);
	}

	/**
	 * Cleanup tracer artifacts for current run.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public static function cleanup_run( $request ) {
		$task_id = absint( $request->get_param( 'task_id' ) );
		$batch_id = sanitize_text_field( $request->get_param( 'batch_id' ) );
		$tracer_run_id = sanitize_text_field( $request->get_param( 'tracer_run_id' ) );
		$reason = sanitize_text_field( $request->get_param( 'reason' ) );
		$cleanup_scope = sanitize_key( (string) $request->get_param( 'cleanup_scope' ) );

		if ( $task_id <= 0 ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => 'Neplatné ID úlohy.',
				),
				400
			);
		}

		if ( '' === $cleanup_scope ) {
			$cleanup_scope = 'task';
		}

		$summary = self::cleanup_tracer_files( $task_id, $batch_id, $cleanup_scope );

		if ( '' !== $tracer_run_id ) {
			delete_transient( 'toptour_tracer_' . $tracer_run_id );
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'task_id' => $task_id,
				'batch_id' => $batch_id,
				'cleanup_scope' => $cleanup_scope,
				'reason' => $reason,
				'summary' => $summary,
			),
			200
		);
	}

	private static function compile_supplemental_history_text( $supplemental_history ) {
		$rows = array();
		$index = 1;
		foreach ( (array) $supplemental_history as $entry ) {
			$clean_entry = trim( sanitize_textarea_field( (string) $entry ) );
			if ( '' === $clean_entry ) {
				continue;
			}
			$rows[] = sprintf( '[%d] %s', $index, $clean_entry );
			$index++;
		}

		return implode( "\n\n", $rows );
	}

	private static function append_supplemental_history_to_task_notes( $task_id, $tracer_run_id, $supplemental_history ) {
		$task_id = absint( $task_id );
		if ( $task_id <= 0 || empty( $supplemental_history ) ) {
			return;
		}

		$task = Toptour_Ref_Collection_Tasks::get_task( $task_id );
		if ( ! $task ) {
			return;
		}

		$current_notes = (string) ( $task->notes ?? '' );
		$append_rows = array();
		$timestamp = current_time( 'mysql' );

		foreach ( array_values( (array) $supplemental_history ) as $index => $entry ) {
			$clean_entry = trim( sanitize_textarea_field( (string) $entry ) );
			if ( '' === $clean_entry ) {
				continue;
			}

			$seq = $index + 1;
			$marker_hash = substr( sha1( $task_id . '|' . $seq . '|' . $clean_entry ), 0, 12 );
			$marker = '[TRACER_SUPPLEMENT#' . $seq . ':' . $marker_hash . ']';
			if ( false !== strpos( $current_notes, $marker ) ) {
				continue;
			}

			$append_rows[] = $marker . ' run=' . sanitize_text_field( (string) $tracer_run_id ) . ' at=' . $timestamp . "\n" . $clean_entry;
		}

		if ( empty( $append_rows ) ) {
			return;
		}

		$header = "\n\n=== Tracer Supplemental History ===\n";
		$append_text = implode( "\n\n", $append_rows );
		$new_notes = rtrim( $current_notes );
		if ( '' === $new_notes ) {
			$new_notes = 'Tracer supplemental history:\n' . $append_text;
		} else {
			$new_notes .= $header . $append_text;
		}

		global $wpdb;
		$wpdb->update(
			Toptour_Ref_Collection_Tasks::get_table_name(),
			array(
				'notes' => sanitize_textarea_field( $new_notes ),
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => $task_id )
		);
	}

	private static function find_outbox_file_for_batch( $batch_id ) {
		$batch_id = sanitize_file_name( (string) $batch_id );
		if ( '' === $batch_id ) {
			return '';
		}

		$paths = Toptour_Ref_AI_Bridge::get_paths();
		$candidate = trailingslashit( $paths['outbox_dir'] ) . $batch_id . '.out.json';

		if ( file_exists( $candidate ) ) {
			return $candidate;
		}

		return '';
	}

	private static function read_json_file( $file_path ) {
		if ( '' === $file_path || ! file_exists( $file_path ) ) {
			return null;
		}

		$raw = file_get_contents( $file_path );
		if ( false === $raw || '' === trim( $raw ) ) {
			return null;
		}

		$decoded = json_decode( $raw, true );
		return is_array( $decoded ) ? $decoded : null;
	}

	private static function cleanup_tracer_files( $task_id, $batch_id, $cleanup_scope = 'task' ) {
		$paths = Toptour_Ref_AI_Bridge::get_paths();
		$task_id = absint( $task_id );
		$batch_id = sanitize_file_name( (string) $batch_id );
		$cleanup_scope = sanitize_key( (string) $cleanup_scope );

		$patterns = [];
		if ( 'batch' === $cleanup_scope && '' !== $batch_id ) {
			$patterns[] = $batch_id . '.json';
			$patterns[] = $batch_id . '.out.json';
			$patterns[] = '*-' . $batch_id . '.json';
			$patterns[] = '*-' . $batch_id . '.out.json';
		} else {
			$patterns[] = 'task-' . $task_id . '-*.json';
			$patterns[] = 'task-' . $task_id . '-*.out.json';
			$patterns[] = '*-task-' . $task_id . '-*.json';
			$patterns[] = '*-task-' . $task_id . '-*.out.json';
			if ( '' !== $batch_id ) {
				$patterns[] = $batch_id . '.json';
				$patterns[] = $batch_id . '.out.json';
				$patterns[] = '*-' . $batch_id . '.json';
				$patterns[] = '*-' . $batch_id . '.out.json';
			}
		}

		$dirs = array(
			'inbox' => $paths['inbox_dir'],
			'outbox' => $paths['outbox_dir'],
			'archive' => $paths['archive_dir'],
			'error' => $paths['error_dir'],
		);

		$summary = array();
		foreach ( $dirs as $dir_key => $dir_path ) {
			$summary[ $dir_key ] = self::cleanup_in_dir_by_patterns( $dir_path, $patterns );
		}

		return $summary;
	}

	private static function cleanup_in_dir_by_patterns( $dir_path, $patterns ) {
		$removed = 0;
		$failed = 0;
		$matched_files = array();

		foreach ( (array) $patterns as $pattern ) {
			if ( '' === trim( (string) $pattern ) ) {
				continue;
			}
			$matches = glob( trailingslashit( $dir_path ) . $pattern );
			if ( ! is_array( $matches ) || empty( $matches ) ) {
				continue;
			}
			$matched_files = array_merge( $matched_files, $matches );
		}

		$matched_files = array_values( array_unique( $matched_files ) );
		foreach ( $matched_files as $file_path ) {
			if ( ! is_file( $file_path ) ) {
				continue;
			}
			if ( @unlink( $file_path ) ) {
				$removed++;
			} else {
				$failed++;
			}
		}

		return array(
			'matched' => count( $matched_files ),
			'removed' => $removed,
			'failed' => $failed,
		);
	}
}

// Register routes when WordPress initializes REST API
add_action( 'rest_api_init', array( 'Toptour_Ref_Debug_Tracer_API', 'register_routes' ) );
