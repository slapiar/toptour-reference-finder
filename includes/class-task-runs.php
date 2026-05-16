<?php
/**
 * Task Runs data class.
 *
 * Stores each execution run for a collection task.
 *
 * @package Toptour_Ref
 * @version 0.2.14
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Toptour_Ref_Task_Runs {

	public static function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'toptour_ref_task_runs';
	}

	public static function get_allowed_statuses() {
		return [ 'running', 'finished', 'failed', 'skipped' ];
	}

	public static function create_run( $task_id, $data = [] ) {
		global $wpdb;

		$status = sanitize_text_field( $data['status'] ?? 'running' );
		if ( ! in_array( $status, self::get_allowed_statuses(), true ) ) {
			$status = 'running';
		}

		$now = current_time( 'mysql' );
		$result = $wpdb->insert(
			self::get_table_name(),
			[
				'task_id'          => absint( $task_id ),
				'started_at'       => sanitize_text_field( $data['started_at'] ?? $now ),
				'finished_at'      => empty( $data['finished_at'] ) ? null : sanitize_text_field( $data['finished_at'] ),
				'status'           => $status,
				'found_count'      => absint( $data['found_count'] ?? 0 ),
				'new_count'        => absint( $data['new_count'] ?? 0 ),
				'duplicate_count'  => absint( $data['duplicate_count'] ?? 0 ),
				'error_count'      => absint( $data['error_count'] ?? 0 ),
				'summary'          => sanitize_textarea_field( $data['summary'] ?? '' ),
				'created_at'       => $now,
				'updated_at'       => $now,
			]
		);

		return $result ? (int) $wpdb->insert_id : false;
	}

	public static function update_run( $run_id, $data ) {
		global $wpdb;

		$update = [
			'updated_at' => current_time( 'mysql' ),
		];

		if ( isset( $data['status'] ) ) {
			$status = sanitize_text_field( $data['status'] );
			if ( in_array( $status, self::get_allowed_statuses(), true ) ) {
				$update['status'] = $status;
			}
		}

		if ( array_key_exists( 'finished_at', $data ) ) {
			$update['finished_at'] = empty( $data['finished_at'] ) ? null : sanitize_text_field( $data['finished_at'] );
		}

		foreach ( [ 'found_count', 'new_count', 'duplicate_count', 'error_count' ] as $metric_key ) {
			if ( isset( $data[ $metric_key ] ) ) {
				$update[ $metric_key ] = absint( $data[ $metric_key ] );
			}
		}

		if ( isset( $data['summary'] ) ) {
			$update['summary'] = sanitize_textarea_field( $data['summary'] );
		}

		$result = $wpdb->update(
			self::get_table_name(),
			$update,
			[ 'id' => absint( $run_id ) ]
		);

		return $result !== false;
	}

	public static function get_latest_run_for_task( $task_id ) {
		global $wpdb;
		$table = self::get_table_name();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE task_id = %d ORDER BY started_at DESC, id DESC LIMIT 1", absint( $task_id ) ) );
	}

	public static function get_runs_for_task( $task_id, $limit = 20 ) {
		global $wpdb;
		$table = self::get_table_name();
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table WHERE task_id = %d ORDER BY started_at DESC, id DESC LIMIT %d", absint( $task_id ), max( 1, absint( $limit ) ) ) );
	}
}
