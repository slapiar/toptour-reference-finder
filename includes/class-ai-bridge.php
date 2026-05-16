<?php
/**
 * AI Bridge class.
 *
 * File-based OpenAI bridge for inbox/outbox JSON processing.
 *
 * @package Toptour_Ref
 * @version 0.2.14
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Toptour_Ref_AI_Bridge {

	const LOCK_OPTION_KEY = 'toptour_ref_ai_bridge_lock';

	public static function get_settings() {
		$defaults = [
			'ai_bridge_enabled' => 0,
			'ai_model' => 'gpt-4o-mini',
			'ai_api_key' => '',
			'ai_max_tokens' => 1800,
			'ai_temperature' => 0.2,
			'ai_batch_limit' => 5,
		];

		return [
			'ai_bridge_enabled' => absint( get_option( 'toptour_ref_ai_bridge_enabled', $defaults['ai_bridge_enabled'] ) ) ? 1 : 0,
			'ai_model' => sanitize_text_field( get_option( 'toptour_ref_ai_model', $defaults['ai_model'] ) ),
			'ai_api_key' => sanitize_text_field( get_option( 'toptour_ref_ai_api_key', $defaults['ai_api_key'] ) ),
			'ai_max_tokens' => max( 300, min( 8000, absint( get_option( 'toptour_ref_ai_max_tokens', $defaults['ai_max_tokens'] ) ) ) ),
			'ai_temperature' => max( 0, min( 1, floatval( get_option( 'toptour_ref_ai_temperature', $defaults['ai_temperature'] ) ) ) ),
			'ai_batch_limit' => max( 1, min( 50, absint( get_option( 'toptour_ref_ai_batch_limit', $defaults['ai_batch_limit'] ) ) ) ),
		];
	}

	public static function save_settings( $input ) {
		$enabled = ! empty( $input['ai_bridge_enabled'] ) ? 1 : 0;
		$model = sanitize_text_field( $input['ai_model'] ?? 'gpt-4o-mini' );
		$api_key = sanitize_text_field( $input['ai_api_key'] ?? '' );
		$max_tokens = max( 300, min( 8000, absint( $input['ai_max_tokens'] ?? 1800 ) ) );
		$temperature = max( 0, min( 1, floatval( $input['ai_temperature'] ?? 0.2 ) ) );
		$batch_limit = max( 1, min( 50, absint( $input['ai_batch_limit'] ?? 5 ) ) );

		update_option( 'toptour_ref_ai_bridge_enabled', $enabled );
		update_option( 'toptour_ref_ai_model', $model );
		update_option( 'toptour_ref_ai_api_key', $api_key );
		update_option( 'toptour_ref_ai_max_tokens', $max_tokens );
		update_option( 'toptour_ref_ai_temperature', $temperature );
		update_option( 'toptour_ref_ai_batch_limit', $batch_limit );

		self::ensure_directories();
		return true;
	}

	public static function get_paths() {
		$upload = wp_upload_dir();
		$base_dir = trailingslashit( $upload['basedir'] ) . 'toptour-ref-ai';

		return [
			'base_dir' => $base_dir,
			'inbox_dir' => trailingslashit( $base_dir ) . 'inbox',
			'outbox_dir' => trailingslashit( $base_dir ) . 'outbox',
			'archive_dir' => trailingslashit( $base_dir ) . 'archive',
			'error_dir' => trailingslashit( $base_dir ) . 'error',
		];
	}

	public static function ensure_directories() {
		$paths = self::get_paths();
		$dirs = [
			$paths['base_dir'],
			$paths['inbox_dir'],
			$paths['outbox_dir'],
			$paths['archive_dir'],
			$paths['error_dir'],
		];

		foreach ( $dirs as $dir ) {
			if ( ! is_dir( $dir ) ) {
				wp_mkdir_p( $dir );
			}
		}
	}

	public static function get_directory_file_stats() {
		self::ensure_directories();
		$paths = self::get_paths();

		return [
			'inbox' => self::get_dir_file_count( $paths['inbox_dir'] ),
			'outbox' => self::get_dir_file_count( $paths['outbox_dir'] ),
			'archive' => self::get_dir_file_count( $paths['archive_dir'] ),
			'error' => self::get_dir_file_count( $paths['error_dir'] ),
		];
	}

	public static function cleanup_files( $scope = 'archive', $older_than_days = 0, $limit = 0, $dry_run = false ) {
		self::ensure_directories();
		$paths = self::get_paths();
		$scope = sanitize_key( (string) $scope );
		$older_than_days = absint( $older_than_days );
		$limit = absint( $limit );
		$dry_run = ! empty( $dry_run );

		$map = [
			'inbox' => $paths['inbox_dir'],
			'outbox' => $paths['outbox_dir'],
			'archive' => $paths['archive_dir'],
			'error' => $paths['error_dir'],
		];

		$scopes = 'all' === $scope ? array_keys( $map ) : [ $scope ];
		$results = [];
		$total_removed = 0;
		$total_failed = 0;
		$total_remaining = 0;

		foreach ( $scopes as $single_scope ) {
			if ( ! isset( $map[ $single_scope ] ) ) {
				continue;
			}

			$dir_result = self::cleanup_single_dir( $map[ $single_scope ], $older_than_days, $limit, $dry_run );
			$results[ $single_scope ] = $dir_result;
			$total_removed += absint( $dir_result['removed'] ?? 0 );
			$total_failed += absint( $dir_result['failed'] ?? 0 );
			$total_remaining += absint( $dir_result['remaining'] ?? 0 );
		}

		return [
			'scope' => $scope,
			'removed' => $total_removed,
			'failed' => $total_failed,
			'remaining' => $total_remaining,
			'dry_run' => $dry_run ? 1 : 0,
			'details' => $results,
		];
	}

	/**
	 * Generate an inbox batch JSON file from an existing Collection Task.
	 *
	 * Reads task data and writes a ready-to-process inbox file into inbox_dir.
	 *
	 * @param int $task_id
	 * @return array { ok: bool, message: string, filename?: string }
	 */
	public static function generate_inbox_batch( $task_id ) {
		$task_id = absint( $task_id );
		if ( ! $task_id ) {
			return [ 'ok' => false, 'message' => 'Neplatné task_id.' ];
		}

		$task = Toptour_Ref_Collection_Tasks::get_task( $task_id );
		if ( ! $task ) {
			return [ 'ok' => false, 'message' => 'Úloha s ID ' . $task_id . ' neexistuje.' ];
		}

		$paths = self::get_paths();
		self::ensure_directories();

		$batch_id = 'task-' . $task_id . '-' . gmdate( 'YmdHis' );

		$question = ! empty( $task->query_text )
			? sanitize_textarea_field( (string) $task->query_text )
			: sanitize_text_field( (string) $task->task_title );

		$context_parts = [];
		if ( ! empty( $task->task_title ) ) {
			$context_parts[] = 'Úloha: ' . sanitize_text_field( (string) $task->task_title );
		}
		if ( ! empty( $task->target_type ) && 'general' !== $task->target_type ) {
			$context_parts[] = 'Cieľ: ' . sanitize_text_field( (string) $task->target_type ) . ( ! empty( $task->target_id ) ? ' #' . absint( $task->target_id ) : '' );
		}
		if ( ! empty( $task->source_hint ) ) {
			$context_parts[] = 'Zdroje: ' . sanitize_textarea_field( (string) $task->source_hint );
		}

		$payload = [
			'version'     => '1.0',
			'batch_id'    => $batch_id,
			'task_id'     => $task_id,
			'question'    => $question,
			'context'     => implode( "\n", $context_parts ),
			'constraints' => '',
		];

		$filename = $batch_id . '.json';
		$filepath = trailingslashit( $paths['inbox_dir'] ) . $filename;

		$encoded = wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
		if ( false === $encoded ) {
			return [ 'ok' => false, 'message' => 'Chyba serializácie JSON.' ];
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		$written = file_put_contents( $filepath, $encoded );
		if ( false === $written ) {
			return [ 'ok' => false, 'message' => 'Súbor sa nepodarilo zapísať do inbox_dir.' ];
		}

		return [ 'ok' => true, 'message' => 'Batch vygenerovaný.', 'filename' => $filename ];
	}

	public static function process_pending_batches( $limit = null ) {
		$lock_token = self::acquire_lock();
		if ( '' === $lock_token ) {
			return [
				'success' => false,
				'message' => 'AI bridge processing already running.',
				'processed' => 0,
				'failed' => 0,
				'outputs' => [],
			];
		}

		try {
		$settings = self::get_settings();
		if ( empty( $settings['ai_bridge_enabled'] ) ) {
			return [
				'success' => false,
				'message' => 'AI bridge je vypnutý.',
				'processed' => 0,
				'failed' => 0,
				'outputs' => [],
			];
		}

		if ( empty( $settings['ai_api_key'] ) ) {
			return [
				'success' => false,
				'message' => 'Chýba OpenAI API key.',
				'processed' => 0,
				'failed' => 0,
				'outputs' => [],
			];
		}

		self::ensure_directories();
		$paths = self::get_paths();
		$batch_limit = null === $limit ? (int) $settings['ai_batch_limit'] : max( 1, absint( $limit ) );

		$inbox_files = glob( trailingslashit( $paths['inbox_dir'] ) . '*.json' );
		if ( ! is_array( $inbox_files ) || empty( $inbox_files ) ) {
			return [
				'success' => true,
				'message' => 'Inbox je prázdny.',
				'processed' => 0,
				'failed' => 0,
				'outputs' => [],
			];
		}

		sort( $inbox_files );
		$processed = 0;
		$failed = 0;
		$outputs = [];

		foreach ( array_slice( $inbox_files, 0, $batch_limit ) as $inbox_file ) {
			$claim = self::claim_file_for_processing( $inbox_file );
			if ( empty( $claim['claimed_path'] ) ) {
				continue;
			}

			$result = self::process_inbox_file( $claim['claimed_path'], $settings, $claim['original_name'], $claim['original_path'] );
			if ( ! empty( $result['success'] ) ) {
				$processed++;
			} else {
				$failed++;
			}
			if ( ! empty( $result['outbox_file'] ) ) {
				$outputs[] = $result['outbox_file'];
			}
		}

		return [
			'success' => true,
			'message' => sprintf( 'Spracované: %d, chybné: %d.', $processed, $failed ),
			'processed' => $processed,
			'failed' => $failed,
			'outputs' => $outputs,
		];
		} finally {
			self::release_lock( $lock_token );
		}
	}

	private static function process_inbox_file( $inbox_file, $settings, $original_name, $original_path ) {
		$paths = self::get_paths();
		$filename = sanitize_file_name( (string) $original_name );
		$raw = file_get_contents( $inbox_file );
		if ( false === $raw || '' === trim( $raw ) ) {
			self::move_to_error( $inbox_file, 'invalid_or_empty_file', $filename );
			return [ 'success' => false ];
		}

		$payload = json_decode( $raw, true );
		if ( ! is_array( $payload ) ) {
			self::move_to_error( $inbox_file, 'invalid_json', $filename );
			return [ 'success' => false ];
		}

		$question = sanitize_textarea_field( (string) ( $payload['question'] ?? '' ) );
		if ( '' === $question ) {
			self::move_to_error( $inbox_file, 'missing_question', $filename );
			return [ 'success' => false ];
		}

		$openai = self::request_openai( $payload, $settings );
		if ( empty( $openai['success'] ) ) {
			$out = self::build_error_output( $payload, $openai['message'] ?? 'OpenAI request failed' );
			$outbox_file = trailingslashit( $paths['outbox_dir'] ) . str_replace( '.json', '.out.json', $filename );
			$written = file_put_contents( $outbox_file, wp_json_encode( $out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
			if ( false === $written ) {
				self::rollback_claim( $inbox_file, $original_path );
				return [ 'success' => false ];
			}
			self::move_to_error( $inbox_file, 'openai_request_failed', $filename );
			return [ 'success' => false, 'outbox_file' => $outbox_file ];
		}

		$structured = self::extract_structured_output( $openai['content'] ?? '' );
		$out = [
			'version' => '1.0',
			'status' => 'ok',
			'generated_at' => gmdate( 'c' ),
			'input' => [
				'batch_id' => sanitize_text_field( (string) ( $payload['batch_id'] ?? '' ) ),
				'task_id' => absint( $payload['task_id'] ?? 0 ),
				'question' => $question,
			],
			'ai' => [
				'model' => sanitize_text_field( (string) ( $settings['ai_model'] ?? '' ) ),
				'raw_response' => sanitize_textarea_field( (string) ( $openai['content'] ?? '' ) ),
			],
			'structured_output' => $structured,
		];

		$validation = self::validate_output_payload( $out );
		if ( empty( $validation['is_valid'] ) ) {
			$out = self::build_error_output( $payload, 'output_validation_failed: ' . sanitize_text_field( $validation['message'] ?? 'invalid output payload' ) );
		} elseif ( isset( $validation['payload'] ) && is_array( $validation['payload'] ) ) {
			$out = $validation['payload'];
		}

		$outbox_file = trailingslashit( $paths['outbox_dir'] ) . str_replace( '.json', '.out.json', $filename );
		$written = file_put_contents( $outbox_file, wp_json_encode( $out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
		if ( false === $written ) {
			self::rollback_claim( $inbox_file, $original_path );
			return [ 'success' => false ];
		}

		$archive_file = trailingslashit( $paths['archive_dir'] ) . $filename;
		rename( $inbox_file, $archive_file );

		return [ 'success' => true, 'outbox_file' => $outbox_file ];
	}

	private static function request_openai( $payload, $settings ) {
		$question = sanitize_textarea_field( (string) ( $payload['question'] ?? '' ) );
		$context = isset( $payload['context'] ) ? wp_json_encode( $payload['context'], JSON_UNESCAPED_UNICODE ) : '{}';
		$constraints = sanitize_textarea_field( (string) ( $payload['constraints'] ?? '' ) );

		$schema_hint = [
			'status' => 'ok|needs_follow_up|error',
			'answer_summary' => 'string',
			'needs_follow_up' => 'boolean',
			'follow_up_question' => 'string',
			'candidate_sources' => [
				[ 'title' => 'string', 'url' => 'string', 'platform' => 'string', 'status' => 'candidate|pending_review' ],
			],
			'candidate_facilities' => [
				[ 'name' => 'string', 'status' => 'possible_match|possible_duplicate|requires_review' ],
			],
			'pending_findings' => [
				[ 'category' => 'string', 'summary' => 'string', 'status' => 'pending_review|candidate|needs_verification' ],
			],
			'photo_evidence_candidates' => [
				[ 'source_url' => 'string', 'status' => 'pending_visual_review' ],
			],
			'import_notes' => [ 'string' ],
		];

		$system = "Si AI modul pre interny system zberu referencii. Nemas pristup do DB. Pracuj len s dodanymi datami. Nikdy netvrd definitivne zavery. Vystup MUSI byt validny JSON objekt podla schema hintu.";
		$user = "OTAZKA:\n" . $question . "\n\nKONTEXT JSON:\n" . $context . "\n\nOBMEDZENIA:\n" . $constraints . "\n\nSCHEMA HINT:\n" . wp_json_encode( $schema_hint, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );

		$body = [
			'model' => sanitize_text_field( (string) ( $settings['ai_model'] ?? 'gpt-4o-mini' ) ),
			'messages' => [
				[ 'role' => 'system', 'content' => $system ],
				[ 'role' => 'user', 'content' => $user ],
			],
			'temperature' => floatval( $settings['ai_temperature'] ?? 0.2 ),
			'max_tokens' => absint( $settings['ai_max_tokens'] ?? 1800 ),
			'response_format' => [
				'type' => 'json_object',
			],
		];

		$response = wp_remote_post(
			'https://api.openai.com/v1/chat/completions',
			[
				'timeout' => 45,
				'headers' => [
					'Content-Type' => 'application/json',
					'Authorization' => 'Bearer ' . sanitize_text_field( (string) $settings['ai_api_key'] ),
				],
				'body' => wp_json_encode( $body ),
			]
		);

		if ( is_wp_error( $response ) ) {
			return [
				'success' => false,
				'message' => sanitize_text_field( $response->get_error_message() ),
			];
		}

		$code = absint( wp_remote_retrieve_response_code( $response ) );
		if ( $code < 200 || $code >= 300 ) {
			$err_body    = (string) wp_remote_retrieve_body( $response );
			$err_decoded = json_decode( $err_body, true );
			$err_detail  = '';
			if ( is_array( $err_decoded ) && isset( $err_decoded['error']['message'] ) ) {
				$err_detail = ' — ' . sanitize_text_field( (string) $err_decoded['error']['message'] );
			} elseif ( '' !== trim( $err_body ) ) {
				$err_detail = ' — ' . sanitize_text_field( substr( $err_body, 0, 300 ) );
			}
			return [
				'success' => false,
				'message' => 'OpenAI HTTP ' . $code . $err_detail,
			];
		}

		$decoded = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$content = '';
		if ( is_array( $decoded ) && isset( $decoded['choices'][0]['message']['content'] ) ) {
			$content = (string) $decoded['choices'][0]['message']['content'];
		}

		if ( '' === trim( $content ) ) {
			return [
				'success' => false,
				'message' => 'OpenAI returned empty content',
			];
		}

		return [
			'success' => true,
			'content' => $content,
		];
	}

	private static function extract_structured_output( $raw_content ) {
		$decoded = json_decode( (string) $raw_content, true );
		if ( ! is_array( $decoded ) ) {
			return [
				'status' => 'error',
				'answer_summary' => 'AI response nebolo možné parse-núť ako JSON.',
				'needs_follow_up' => true,
				'follow_up_question' => 'Prosím zopakuj otázku s presnejším kontextom.',
				'candidate_sources' => [],
				'candidate_facilities' => [],
				'pending_findings' => [],
				'photo_evidence_candidates' => [],
				'import_notes' => [ 'invalid_ai_json' ],
			];
		}

		return [
			'status' => sanitize_text_field( (string) ( $decoded['status'] ?? 'ok' ) ),
			'answer_summary' => sanitize_textarea_field( (string) ( $decoded['answer_summary'] ?? '' ) ),
			'needs_follow_up' => ! empty( $decoded['needs_follow_up'] ),
			'follow_up_question' => sanitize_textarea_field( (string) ( $decoded['follow_up_question'] ?? '' ) ),
			'candidate_sources' => is_array( $decoded['candidate_sources'] ?? null ) ? $decoded['candidate_sources'] : [],
			'candidate_facilities' => is_array( $decoded['candidate_facilities'] ?? null ) ? $decoded['candidate_facilities'] : [],
			'pending_findings' => is_array( $decoded['pending_findings'] ?? null ) ? $decoded['pending_findings'] : [],
			'photo_evidence_candidates' => is_array( $decoded['photo_evidence_candidates'] ?? null ) ? $decoded['photo_evidence_candidates'] : [],
			'import_notes' => is_array( $decoded['import_notes'] ?? null ) ? $decoded['import_notes'] : [],
		];
	}

	private static function build_error_output( $payload, $message ) {
		return [
			'version' => '1.0',
			'status' => 'error',
			'generated_at' => gmdate( 'c' ),
			'input' => [
				'batch_id' => sanitize_text_field( (string) ( $payload['batch_id'] ?? '' ) ),
				'task_id' => absint( $payload['task_id'] ?? 0 ),
				'question' => sanitize_textarea_field( (string) ( $payload['question'] ?? '' ) ),
			],
			'error' => sanitize_text_field( $message ),
			'structured_output' => [
				'status' => 'error',
				'answer_summary' => '',
				'needs_follow_up' => true,
				'follow_up_question' => 'Doplň prosím kontext a skús otázku znova.',
				'candidate_sources' => [],
				'candidate_facilities' => [],
				'pending_findings' => [],
				'photo_evidence_candidates' => [],
				'import_notes' => [ 'openai_error' ],
			],
		];
	}

	private static function move_to_error( $inbox_file, $reason, $original_name = '' ) {
		$paths = self::get_paths();
		self::ensure_directories();
		$filename = '' === $original_name ? basename( $inbox_file ) : sanitize_file_name( (string) $original_name );
		$target = trailingslashit( $paths['error_dir'] ) . sanitize_file_name( $reason ) . '-' . $filename;
		rename( $inbox_file, $target );
	}

	private static function claim_file_for_processing( $file_path ) {
		$file_path = (string) $file_path;
		if ( '' === $file_path || ! is_file( $file_path ) ) {
			return [];
		}

		$claimed_path = $file_path . '.processing';
		$ok = @rename( $file_path, $claimed_path );
		if ( ! $ok ) {
			return [];
		}

		return [
			'claimed_path' => $claimed_path,
			'original_path' => $file_path,
			'original_name' => sanitize_file_name( basename( $file_path ) ),
		];
	}

	private static function rollback_claim( $claimed_path, $original_path ) {
		$claimed_path = (string) $claimed_path;
		$original_path = (string) $original_path;
		if ( '' === $claimed_path || '' === $original_path || ! is_file( $claimed_path ) ) {
			return false;
		}
		return @rename( $claimed_path, $original_path );
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

	private static function validate_output_payload( $payload ) {
		if ( ! is_array( $payload ) ) {
			return [
				'is_valid' => false,
				'message' => 'Payload is not an object.',
			];
		}

		$normalized = $payload;
		$normalized['version'] = sanitize_text_field( (string) ( $payload['version'] ?? '' ) );
		$normalized['status'] = sanitize_text_field( (string) ( $payload['status'] ?? '' ) );
		$normalized['generated_at'] = sanitize_text_field( (string) ( $payload['generated_at'] ?? '' ) );

		if ( '1.0' !== $normalized['version'] ) {
			return [
				'is_valid' => false,
				'message' => 'Invalid version.',
			];
		}

		if ( ! in_array( $normalized['status'], [ 'ok', 'error' ], true ) ) {
			return [
				'is_valid' => false,
				'message' => 'Invalid top-level status.',
			];
		}

		if ( '' === $normalized['generated_at'] || false === strtotime( $normalized['generated_at'] ) ) {
			return [
				'is_valid' => false,
				'message' => 'Invalid generated_at datetime.',
			];
		}

		if ( empty( $payload['input'] ) || ! is_array( $payload['input'] ) ) {
			return [
				'is_valid' => false,
				'message' => 'Missing input object.',
			];
		}

		$normalized['input'] = [
			'batch_id' => sanitize_text_field( (string) ( $payload['input']['batch_id'] ?? '' ) ),
			'task_id' => absint( $payload['input']['task_id'] ?? 0 ),
			'question' => sanitize_textarea_field( (string) ( $payload['input']['question'] ?? '' ) ),
		];

		if ( '' === $normalized['input']['question'] ) {
			return [
				'is_valid' => false,
				'message' => 'Missing input.question.',
			];
		}

		$normalized['ai'] = is_array( $payload['ai'] ?? null ) ? [
			'model' => sanitize_text_field( (string) ( $payload['ai']['model'] ?? '' ) ),
			'raw_response' => sanitize_textarea_field( (string) ( $payload['ai']['raw_response'] ?? '' ) ),
		] : [];

		if ( empty( $payload['structured_output'] ) || ! is_array( $payload['structured_output'] ) ) {
			return [
				'is_valid' => false,
				'message' => 'Missing structured_output object.',
			];
		}

		$structured_validation = self::validate_structured_output( $payload['structured_output'] );
		if ( empty( $structured_validation['is_valid'] ) ) {
			return $structured_validation;
		}

		$normalized['structured_output'] = $structured_validation['structured_output'];

		if ( ! empty( $payload['error'] ) ) {
			$normalized['error'] = sanitize_text_field( (string) $payload['error'] );
		}

		return [
			'is_valid' => true,
			'payload' => $normalized,
		];
	}

	private static function validate_structured_output( $structured ) {
		if ( ! is_array( $structured ) ) {
			return [
				'is_valid' => false,
				'message' => 'structured_output must be object.',
			];
		}

		$status = sanitize_text_field( (string) ( $structured['status'] ?? '' ) );
		if ( ! in_array( $status, [ 'ok', 'needs_follow_up', 'error' ], true ) ) {
			return [
				'is_valid' => false,
				'message' => 'Invalid structured_output.status.',
			];
		}

		$normalized = [
			'status' => $status,
			'answer_summary' => sanitize_textarea_field( (string) ( $structured['answer_summary'] ?? '' ) ),
			'needs_follow_up' => ! empty( $structured['needs_follow_up'] ),
			'follow_up_question' => sanitize_textarea_field( (string) ( $structured['follow_up_question'] ?? '' ) ),
			'candidate_sources' => self::normalize_candidate_sources( $structured['candidate_sources'] ?? [] ),
			'candidate_facilities' => self::normalize_candidate_facilities( $structured['candidate_facilities'] ?? [] ),
			'pending_findings' => self::normalize_pending_findings( $structured['pending_findings'] ?? [] ),
			'photo_evidence_candidates' => self::normalize_photo_candidates( $structured['photo_evidence_candidates'] ?? [] ),
			'import_notes' => self::normalize_import_notes( $structured['import_notes'] ?? [] ),
		];

		return [
			'is_valid' => true,
			'structured_output' => $normalized,
		];
	}

	private static function normalize_candidate_sources( $rows ) {
		if ( ! is_array( $rows ) ) {
			return [];
		}

		$normalized = [];
		$allowed_status = [ 'candidate', 'pending_review', 'needs_verification' ];
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$status = sanitize_text_field( (string) ( $row['status'] ?? 'candidate' ) );
			if ( ! in_array( $status, $allowed_status, true ) ) {
				$status = 'candidate';
			}
			$normalized[] = [
				'title' => sanitize_text_field( (string) ( $row['title'] ?? '' ) ),
				'url' => esc_url_raw( (string) ( $row['url'] ?? '' ) ),
				'platform' => sanitize_text_field( (string) ( $row['platform'] ?? '' ) ),
				'status' => $status,
				'task_id' => absint( $row['task_id'] ?? 0 ),
				'source_id' => absint( $row['source_id'] ?? 0 ),
				'facility_id' => absint( $row['facility_id'] ?? 0 ),
				'destination_id' => absint( $row['destination_id'] ?? 0 ),
				'notes' => sanitize_textarea_field( (string) ( $row['notes'] ?? '' ) ),
			];
		}

		return $normalized;
	}

	private static function normalize_candidate_facilities( $rows ) {
		if ( ! is_array( $rows ) ) {
			return [];
		}

		$normalized = [];
		$allowed_status = [ 'possible_match', 'possible_duplicate', 'requires_review', 'pending_review' ];
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$status = sanitize_text_field( (string) ( $row['status'] ?? 'requires_review' ) );
			if ( ! in_array( $status, $allowed_status, true ) ) {
				$status = 'requires_review';
			}
			$normalized[] = [
				'name' => sanitize_text_field( (string) ( $row['name'] ?? '' ) ),
				'status' => $status,
				'task_id' => absint( $row['task_id'] ?? 0 ),
				'facility_id' => absint( $row['facility_id'] ?? 0 ),
				'destination_id' => absint( $row['destination_id'] ?? 0 ),
				'notes' => sanitize_textarea_field( (string) ( $row['notes'] ?? '' ) ),
			];
		}

		return $normalized;
	}

	private static function normalize_pending_findings( $rows ) {
		if ( ! is_array( $rows ) ) {
			return [];
		}

		$normalized = [];
		$allowed_status = [ 'pending_review', 'candidate', 'needs_verification' ];
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$status = sanitize_text_field( (string) ( $row['status'] ?? 'pending_review' ) );
			if ( ! in_array( $status, $allowed_status, true ) ) {
				$status = 'pending_review';
			}
			$normalized[] = [
				'category' => sanitize_key( (string) ( $row['category'] ?? '' ) ),
				'summary' => sanitize_textarea_field( (string) ( $row['summary'] ?? '' ) ),
				'status' => $status,
				'task_id' => absint( $row['task_id'] ?? 0 ),
				'source_id' => absint( $row['source_id'] ?? 0 ),
				'facility_id' => absint( $row['facility_id'] ?? 0 ),
				'destination_id' => absint( $row['destination_id'] ?? 0 ),
				'notes' => sanitize_textarea_field( (string) ( $row['notes'] ?? '' ) ),
			];
		}

		return $normalized;
	}

	private static function normalize_photo_candidates( $rows ) {
		if ( ! is_array( $rows ) ) {
			return [];
		}

		$normalized = [];
		$allowed_status = [ 'pending_visual_review', 'candidate', 'needs_verification' ];
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$status = sanitize_text_field( (string) ( $row['status'] ?? 'pending_visual_review' ) );
			if ( ! in_array( $status, $allowed_status, true ) ) {
				$status = 'pending_visual_review';
			}
			$normalized[] = [
				'source_url' => esc_url_raw( (string) ( $row['source_url'] ?? '' ) ),
				'status' => $status,
				'task_id' => absint( $row['task_id'] ?? 0 ),
				'source_id' => absint( $row['source_id'] ?? 0 ),
				'facility_id' => absint( $row['facility_id'] ?? 0 ),
				'destination_id' => absint( $row['destination_id'] ?? 0 ),
				'notes' => sanitize_textarea_field( (string) ( $row['notes'] ?? '' ) ),
			];
		}

		return $normalized;
	}

	private static function normalize_import_notes( $notes ) {
		if ( ! is_array( $notes ) ) {
			return [];
		}

		$normalized = [];
		foreach ( $notes as $note ) {
			$clean = sanitize_text_field( (string) $note );
			if ( '' !== $clean ) {
				$normalized[] = $clean;
			}
		}

		return array_values( array_unique( $normalized ) );
	}

	private static function get_dir_file_count( $dir ) {
		$entries = @scandir( $dir );
		if ( ! is_array( $entries ) ) {
			return 0;
		}

		$count = 0;
		foreach ( $entries as $entry ) {
			if ( '.' === $entry || '..' === $entry ) {
				continue;
			}
			$path = trailingslashit( $dir ) . $entry;
			if ( is_file( $path ) ) {
				$count++;
			}
		}

		return $count;
	}

	private static function cleanup_single_dir( $dir, $older_than_days, $limit, $dry_run = false ) {
		$entries = @scandir( $dir );
		if ( ! is_array( $entries ) ) {
			return [
				'removed' => 0,
				'failed' => 0,
				'remaining' => 0,
				'eligible' => 0,
				'would_remove' => 0,
				'scanned' => 0,
			];
		}

		$files = [];
		foreach ( $entries as $entry ) {
			if ( '.' === $entry || '..' === $entry ) {
				continue;
			}
			$path = trailingslashit( $dir ) . $entry;
			if ( ! is_file( $path ) ) {
				continue;
			}
			$files[] = [
				'path' => $path,
				'mtime' => (int) @filemtime( $path ),
			];
		}

		usort(
			$files,
			static function ( $a, $b ) {
				return (int) $a['mtime'] <=> (int) $b['mtime'];
			}
		);

		$cutoff = $older_than_days > 0 ? ( current_time( 'timestamp' ) - ( $older_than_days * DAY_IN_SECONDS ) ) : 0;
		$removed = 0;
		$failed = 0;
		$eligible = 0;
		$handled = 0;

		foreach ( $files as $file ) {
			$mtime = absint( $file['mtime'] );
			if ( $cutoff > 0 && $mtime > 0 && $mtime >= $cutoff ) {
				continue;
			}

			if ( $limit > 0 && $handled >= $limit ) {
				break;
			}

			$eligible++;

			if ( $dry_run ) {
				$handled++;
				continue;
			}

			$ok = @unlink( $file['path'] );
			if ( $ok ) {
				$removed++;
			} else {
				$failed++;
			}

			$handled++;
		}

		$remaining = $dry_run ? self::get_dir_file_count( $dir ) : self::get_dir_file_count( $dir );
		return [
			'removed' => $removed,
			'failed' => $failed,
			'remaining' => $remaining,
			'eligible' => $eligible,
			'would_remove' => $dry_run ? $eligible : 0,
			'scanned' => count( $files ),
		];
	}
}
