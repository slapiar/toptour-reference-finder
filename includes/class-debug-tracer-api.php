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
	}

	/**
	 * Check REST API permissions.
	 *
	 * @return bool
	 */
	public static function check_permissions() {
		return current_user_can( 'manage_toptour_references' );
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

		if ( $task_id <= 0 ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => 'Neplatné ID úlohy.',
				),
				400
			);
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
			if ( is_array( $batch_payload ) && isset( $batch_payload['input']['records'] ) ) {
				$record_count = count( (array) $batch_payload['input']['records'] );
			}
		}

		// Extract batch ID
		$batch_id = $batch_result['batch_id'] ?? md5( $batch_result['filename'] );

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

		// In a real implementation, this would call the AI service
		// For now, we return a mock response structure with sample data
		$ai_response = array(
			'status' => 'processed',
			'batch_id' => $batch_id,
			'model' => $settings['ai_model'],
			'timestamp' => current_time( 'mysql' ),
			'processing_time_ms' => rand(500, 3000),
			// Mock structured output with sample data
			'structured_output' => array(
				'findings' => array(
					array(
						'title' => 'Pozitívny signál: Vysoká obsadenosť v letnej sezóne',
						'sentiment' => 'positive',
						'confidence' => 0.92,
						'source_url' => 'https://example.com/review',
						'category' => 'occupancy',
					),
					array(
						'title' => 'Bezpečnostný problém: Chýbajúce hygijenické opatrenia',
						'sentiment' => 'negative',
						'confidence' => 0.78,
						'source_url' => 'https://example.com/complaint',
						'category' => 'safety',
					),
				),
				'photo_evidence' => array(
					array(
						'url' => 'https://example.com/photo1.jpg',
						'description' => 'Spoločenská miestnosť',
						'caption' => 'Moderné zariadenie',
					),
					array(
						'url' => 'https://example.com/photo2.jpg',
						'description' => 'Vonkajší bazén',
						'caption' => 'Čistý a dobre udržiavaný',
					),
				),
				'sources' => array(
					array(
						'url' => 'https://tripadvisor.com/hotel/test',
						'title' => 'Hotel na TripAdvisor',
						'credibility' => 'high',
						'source_type' => 'review_platform',
					),
					array(
						'url' => 'https://booking.com/hotel/test',
						'title' => 'Hotel na Booking.com',
						'credibility' => 'high',
						'source_type' => 'review_platform',
					),
				),
				'summary' => 'Analýza identifikovala 2 kľúčové nálezy s vysokou dôveryhodnosťou. Pozitívne signály dominujú.',
			),
		);

		return new WP_REST_Response(
			array(
				'success' => true,
				'batch_id' => $batch_id,
				'ai_model' => $settings['ai_model'],
				'tokens_used' => 1500, // Mock token count
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
		$import_result = Toptour_Ref_AI_Outbox_Importer::process_pending_outbox( 1 );

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
					$photo_url = $photo->photo_url ?? '';
					$photos[] = array(
						'id' => $photo->id ?? 0,
						'description' => $photo->photo_title ?? $photo->description ?? '',
						'photo_url' => $photo_url,
						'thumbnail_url' => $photo_url, // Same URL used for thumbnail
					);
				}
			}
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'batch_id' => $batch_id,
				'findings_created' => count( (array) $findings ),
				'photos_created' => count( $photos ),
				'sources_processed' => $import_result['processed'] ?? 0,
				'findings' => $findings,
				'photos' => $photos,
			),
			200
		);
	}
}

// Register routes when WordPress initializes REST API
add_action( 'rest_api_init', array( 'Toptour_Ref_Debug_Tracer_API', 'register_routes' ) );
