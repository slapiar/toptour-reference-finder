<?php
/**
 * Discovery Runs helper class.
 *
 * Tracks controlled discovery attempts for collection tasks.
 *
 * @package Toptour_Ref
 * @version 0.2.14
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Toptour_Ref_Discovery_Runs {

	public static function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'toptour_ref_discovery_runs';
	}

	public static function get_missing_fields_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'toptour_ref_discovery_missing_fields';
	}

	public static function get_allowed_statuses() {
		return [ 'draft', 'needs_input', 'ready', 'running', 'completed', 'failed', 'archived' ];
	}

	public static function get_allowed_providers() {
		return [ 'manual', 'search_api', 'future_provider' ];
	}

	public static function get_allowed_field_types() {
		return [ 'text', 'textarea', 'select', 'number', 'url', 'email' ];
	}

	public static function get_allowed_field_statuses() {
		return [ 'missing', 'provided', 'ignored' ];
	}

	public static function create_run( $data ) {
		global $wpdb;

		$status = in_array( $data['run_status'] ?? 'draft', self::get_allowed_statuses(), true ) ? $data['run_status'] : 'draft';
		$provider = in_array( $data['discovery_provider'] ?? 'manual', self::get_allowed_providers(), true ) ? $data['discovery_provider'] : 'manual';
		$now = current_time( 'mysql' );

		$result = $wpdb->insert(
			self::get_table_name(),
			[
				'collection_task_id'      => absint( $data['collection_task_id'] ?? 0 ),
				'run_title'               => sanitize_text_field( $data['run_title'] ?? '' ),
				'input_summary'           => isset( $data['input_summary'] ) ? wp_json_encode( $data['input_summary'] ) : null,
				'resolved_target_type'    => sanitize_text_field( $data['resolved_target_type'] ?? 'general' ),
				'resolved_target_id'      => absint( $data['resolved_target_id'] ?? 0 ),
				'resolved_target_label'   => sanitize_text_field( $data['resolved_target_label'] ?? '' ),
				'detected_destination'    => sanitize_text_field( $data['detected_destination'] ?? '' ),
				'detected_facility'       => sanitize_text_field( $data['detected_facility'] ?? '' ),
				'detected_interests'      => wp_json_encode( $data['detected_interests'] ?? [] ),
				'detected_finding_areas'  => wp_json_encode( $data['detected_finding_areas'] ?? [] ),
				'missing_fields'          => wp_json_encode( $data['missing_fields'] ?? [] ),
				'search_queries'          => wp_json_encode( $data['search_queries'] ?? [] ),
				'discovery_provider'      => $provider,
				'run_status'              => $status,
				'run_notes'               => sanitize_textarea_field( $data['run_notes'] ?? '' ),
				'created_at'              => $now,
				'updated_at'              => $now,
				'completed_at'            => null,
			]
		);

		if ( ! $result ) {
			return false;
		}

		$run_id = (int) $wpdb->insert_id;
		if ( ! empty( $data['missing_fields'] ) && is_array( $data['missing_fields'] ) ) {
			self::update_run_missing_fields( $run_id, $data['missing_fields'] );
		}

		return $run_id;
	}

	public static function get_run( $run_id ) {
		global $wpdb;
		$table = self::get_table_name();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", absint( $run_id ) ) );
	}

	public static function get_runs_for_task( $collection_task_id ) {
		global $wpdb;
		$table = self::get_table_name();
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table WHERE collection_task_id = %d ORDER BY created_at DESC", absint( $collection_task_id ) ) );
	}

	public static function get_latest_run_for_task( $collection_task_id ) {
		$runs = self::get_runs_for_task( $collection_task_id );
		return ! empty( $runs ) ? $runs[0] : null;
	}

	public static function update_run_status( $run_id, $status ) {
		global $wpdb;

		if ( ! in_array( $status, self::get_allowed_statuses(), true ) ) {
			return false;
		}

		$update = [
			'run_status' => $status,
			'updated_at' => current_time( 'mysql' ),
		];

		if ( 'completed' === $status ) {
			$update['completed_at'] = current_time( 'mysql' );
		}

		$result = $wpdb->update(
			self::get_table_name(),
			$update,
			[ 'id' => absint( $run_id ) ]
		);

		return $result !== false;
	}

	public static function update_run_missing_fields( $run_id, $fields ) {
		global $wpdb;

		if ( ! is_array( $fields ) ) {
			return false;
		}

		$serialized = wp_json_encode( $fields );
		$wpdb->update(
			self::get_table_name(),
			[
				'missing_fields' => $serialized,
				'updated_at'     => current_time( 'mysql' ),
			],
			[ 'id' => absint( $run_id ) ]
		);

		$table = self::get_missing_fields_table_name();
		$existing = self::get_missing_fields( $run_id );
		$existing_by_key = [];
		foreach ( $existing as $row ) {
			$existing_by_key[ $row->field_key ] = $row;
		}

		$now = current_time( 'mysql' );
		foreach ( $fields as $field ) {
			$field_key = sanitize_key( $field['field_key'] ?? '' );
			if ( '' === $field_key ) {
				continue;
			}

			$field_type = sanitize_text_field( $field['field_type'] ?? 'text' );
			if ( ! in_array( $field_type, self::get_allowed_field_types(), true ) ) {
				$field_type = 'text';
			}

			$field_status = sanitize_text_field( $field['field_status'] ?? 'missing' );
			if ( ! in_array( $field_status, self::get_allowed_field_statuses(), true ) ) {
				$field_status = 'missing';
			}

			$field_value = sanitize_textarea_field( $field['field_value'] ?? '' );
			$is_required = ! empty( $field['is_required'] ) ? 1 : 0;

			$payload = [
				'field_label'         => sanitize_text_field( $field['field_label'] ?? $field_key ),
				'field_type'          => $field_type,
				'field_value'         => $field_value,
				'is_required'         => $is_required,
				'field_status'        => $field_status,
				'help_text'           => sanitize_textarea_field( $field['help_text'] ?? '' ),
				'updated_at'          => $now,
			];

			if ( isset( $existing_by_key[ $field_key ] ) ) {
				$wpdb->update( $table, $payload, [ 'id' => (int) $existing_by_key[ $field_key ]->id ] );
			} else {
				$wpdb->insert(
					$table,
					array_merge(
						$payload,
						[
							'discovery_run_id'    => absint( $run_id ),
							'collection_task_id'  => absint( $field['collection_task_id'] ?? 0 ),
							'field_key'           => $field_key,
							'created_at'          => $now,
						]
					)
				);
			}
		}

		return true;
	}

	public static function create_missing_field( $data ) {
		global $wpdb;
		$table = self::get_missing_fields_table_name();
		$now = current_time( 'mysql' );

		$field_type = sanitize_text_field( $data['field_type'] ?? 'text' );
		if ( ! in_array( $field_type, self::get_allowed_field_types(), true ) ) {
			$field_type = 'text';
		}

		$field_status = sanitize_text_field( $data['field_status'] ?? 'missing' );
		if ( ! in_array( $field_status, self::get_allowed_field_statuses(), true ) ) {
			$field_status = 'missing';
		}

		$result = $wpdb->insert(
			$table,
			[
				'discovery_run_id'   => absint( $data['discovery_run_id'] ?? 0 ),
				'collection_task_id' => absint( $data['collection_task_id'] ?? 0 ),
				'field_key'          => sanitize_key( $data['field_key'] ?? '' ),
				'field_label'        => sanitize_text_field( $data['field_label'] ?? '' ),
				'field_type'         => $field_type,
				'field_value'        => sanitize_textarea_field( $data['field_value'] ?? '' ),
				'is_required'        => ! empty( $data['is_required'] ) ? 1 : 0,
				'field_status'       => $field_status,
				'help_text'          => sanitize_textarea_field( $data['help_text'] ?? '' ),
				'created_at'         => $now,
				'updated_at'         => $now,
			]
		);

		return $result ? (int) $wpdb->insert_id : false;
	}

	public static function get_missing_fields( $run_id ) {
		global $wpdb;
		$table = self::get_missing_fields_table_name();
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table WHERE discovery_run_id = %d ORDER BY id ASC", absint( $run_id ) ) );
	}

	public static function save_missing_field_values( $run_id, $values ) {
		global $wpdb;

		$rows = self::get_missing_fields( $run_id );
		if ( empty( $rows ) ) {
			return false;
		}

		$table = self::get_missing_fields_table_name();
		$updated = false;
		foreach ( $rows as $row ) {
			$field_key = $row->field_key;
			if ( ! isset( $values[ $field_key ] ) ) {
				continue;
			}

			$field_value = sanitize_textarea_field( wp_unslash( $values[ $field_key ] ) );
			$status = $field_value !== '' ? 'provided' : 'missing';
			$wpdb->update(
				$table,
				[
					'field_value' => $field_value,
					'field_status' => $status,
					'updated_at' => current_time( 'mysql' ),
				],
				[ 'id' => (int) $row->id ]
			);
			$updated = true;
		}

		self::sync_missing_fields_json( $run_id );
		self::refresh_run_status_from_missing_fields( $run_id );
		return $updated;
	}

	public static function sync_missing_fields_json( $run_id ) {
		global $wpdb;
		$rows = self::get_missing_fields( $run_id );
		$export = [];
		foreach ( $rows as $row ) {
			$export[] = [
				'field_key' => $row->field_key,
				'field_label' => $row->field_label,
				'field_type' => $row->field_type,
				'field_value' => $row->field_value,
				'is_required' => (int) $row->is_required,
				'field_status' => $row->field_status,
				'help_text' => $row->help_text,
			];
		}

		$wpdb->update(
			self::get_table_name(),
			[
				'missing_fields' => wp_json_encode( $export ),
				'updated_at' => current_time( 'mysql' ),
			],
			[ 'id' => absint( $run_id ) ]
		);
	}

	public static function refresh_run_status_from_missing_fields( $run_id ) {
		$rows = self::get_missing_fields( $run_id );
		$has_required_missing = false;
		foreach ( $rows as $row ) {
			if ( (int) $row->is_required === 1 && 'provided' !== $row->field_status ) {
				$has_required_missing = true;
				break;
			}
		}

		if ( $has_required_missing ) {
			return self::update_run_status( $run_id, 'needs_input' );
		}

		return self::update_run_status( $run_id, 'ready' );
	}

	public static function update_run_search_queries( $run_id, $queries ) {
		global $wpdb;

		if ( ! is_array( $queries ) ) {
			return false;
		}

		$sanitized = [];
		foreach ( $queries as $query ) {
			$query = sanitize_text_field( $query );
			if ( '' !== $query ) {
				$sanitized[] = $query;
			}
		}

		$result = $wpdb->update(
			self::get_table_name(),
			[
				'search_queries' => wp_json_encode( array_values( array_unique( $sanitized ) ) ),
				'updated_at' => current_time( 'mysql' ),
			],
			[ 'id' => absint( $run_id ) ]
		);

		return $result !== false;
	}

	public static function archive_run( $run_id ) {
		return self::update_run_status( $run_id, 'archived' );
	}
}
