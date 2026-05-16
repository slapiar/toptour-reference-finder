<?php
/**
 * Task Events data class.
 *
 * Stores audit timeline for collection tasks.
 *
 * @package Toptour_Ref
 * @version 0.2.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Toptour_Ref_Task_Events {

	public static function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'toptour_ref_task_events';
	}

	public static function get_allowed_event_types() {
		return [
			'created',
			'updated',
			'enabled',
			'disabled',
			'frequency_changed',
			'query_changed',
			'run_started',
			'run_attempt_started',
			'run_suspect',
			'run_retry_scheduled',
			'run_reset_automatic',
			'run_failed_max_attempts',
			'run_finished',
			'run_skipped',
			'finding_added',
			'finding_accepted',
			'finding_rejected',
			'poi_suggested',
			'poi_accepted',
			'reference_analysis_created',
			'reference_analysis_updated',
			'offer_snapshot_created',
			'offer_snapshot_updated',
			'poi_candidate_suggested',
			'poi_candidate_accepted',
			'poi_candidate_rejected',
			'error',
			'manual_note_added',
		];
	}

	public static function log_event( $task_id, $event_type, $old_value = null, $new_value = null, $note = '' ) {
		global $wpdb;

		$event_type = sanitize_text_field( $event_type );
		if ( ! in_array( $event_type, self::get_allowed_event_types(), true ) ) {
			$event_type = 'updated';
		}

		$old_payload = self::normalize_event_payload( $old_value );
		$new_payload = self::normalize_event_payload( $new_value );

		$result = $wpdb->insert(
			self::get_table_name(),
			[
				'task_id'     => absint( $task_id ),
				'event_type'  => $event_type,
				'old_value'   => $old_payload,
				'new_value'   => $new_payload,
				'note'        => sanitize_textarea_field( $note ),
				'created_by'  => get_current_user_id() ? (int) get_current_user_id() : 0,
				'created_at'  => current_time( 'mysql' ),
			]
		);

		return $result ? (int) $wpdb->insert_id : false;
	}

	public static function get_events_for_task( $task_id, $limit = 100 ) {
		global $wpdb;
		$table = self::get_table_name();
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table WHERE task_id = %d ORDER BY created_at DESC, id DESC LIMIT %d", absint( $task_id ), max( 1, absint( $limit ) ) ) );
	}

	private static function normalize_event_payload( $value ) {
		if ( null === $value || '' === $value ) {
			return null;
		}

		if ( is_array( $value ) || is_object( $value ) ) {
			$encoded = wp_json_encode( $value );
			return is_string( $encoded ) ? $encoded : null;
		}

		return sanitize_textarea_field( (string) $value );
	}
}
